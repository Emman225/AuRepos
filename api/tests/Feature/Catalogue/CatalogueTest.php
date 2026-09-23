<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\TypeLogement;
use Database\Factories\ResidenceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function gestionnaireConnecte(Profil $profil = Profil::Gestionnaire): User
{
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

/** Rattache le gestionnaire actuellement connecté aux résidences données (CdC § 9.5) — sans effet pour un autre profil. */
function visiblePar(User $gestionnaire, Residence|Logement ...$elements): void
{
    $ids = array_unique(array_map(fn ($e) => $e instanceof Logement ? $e->residence_id : $e->id, $elements));
    $gestionnaire->residences()->syncWithoutDetaching($ids);
}

function saisieLogement(array $surcharge = []): array
{
    $type = TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3]);

    return [
        'type_logement_id' => $type->id, 'nom' => 'Appartement A12', 'nombre_pieces' => 3, 'nombre_chambres' => 2,
        'capacite_de_base' => 2, 'capacite_maximale' => 4, 'caution' => 50000, ...$surcharge,
    ];
}

// ---------------------------------------------------------------- accès

it('réserve le catalogue du back office au personnel d’exploitation', function (Profil $profil, int $statut): void {
    gestionnaireConnecte($profil);

    test()->getJson('/api/v1/backoffice/residences')->assertStatus($statut);
})->with([
    [Profil::SuperAdministrateur, 200], [Profil::Administrateur, 200], [Profil::Gestionnaire, 200],
    [Profil::Gouvernante, 403], [Profil::Proprietaire, 403], [Profil::AgentTerrain, 403], [Profil::Client, 403],
]);

// ---------------------------------------------------------------- résidences

it('crée une résidence avec son lieu, ses équipements communs et une adresse lisible', function (): void {
    $auteur = gestionnaireConnecte();
    $proprietaire = Proprietaire::factory()->create();
    $quartier = ResidenceFactory::unQuartier('Cocody', 'Riviera Palmeraie');
    $piscine = Equipement::create(['nom' => 'Piscine', 'portee' => 'residence']);

    $reponse = test()->postJson('/api/v1/backoffice/residences', [
        'proprietaire_id' => $proprietaire->id, 'quartier_id' => $quartier->id, 'nom' => 'Résidence Les Palmiers',
        'adresse' => 'Rue des Jardins, lot 45', 'equipements' => [$piscine->id],
    ])->assertCreated();

    expect($reponse->json('data.slug'))->toBe('residence-les-palmiers')
        ->and($reponse->json('data.lieu.libelle'))->toBe('Cocody › Riviera Palmeraie')
        ->and($reponse->json('data.equipements.0.nom'))->toBe('Piscine')
        ->and($reponse->json('data.disponibilite'))->toBe('disponible')
        ->and($reponse->json('data.mode_vente'))->toBe('logement');

    expect(EntreeAudit::where('sujet_type', 'Residence')->where('user_id', $auteur->id)->sole()->recit)
        ->toBe('Création : résidence « Résidence Les Palmiers ».');
});

it('donne des adresses distinctes à deux résidences du même nom et ne la change pas au renommage', function (): void {
    $a = Residence::factory()->create(['nom' => 'Résidence Bellevue']);
    $b = Residence::factory()->create(['nom' => 'Résidence Bellevue']);

    $a->update(['nom' => 'Résidence Belle Vue II']);

    expect($a->slug)->toBe('residence-bellevue')->and($b->slug)->toBe('residence-bellevue-2');
});

it('refuse un équipement de logement sur une résidence', function (): void {
    $auteur = gestionnaireConnecte();
    $wifi = Equipement::create(['nom' => 'Wifi', 'portee' => 'logement']);
    $residence = Residence::factory()->create();
    visiblePar($auteur, $residence);

    test()->putJson("/api/v1/backoffice/residences/{$residence->id}", ['equipements' => [$wifi->id]])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['equipements.0']]);
});

it('refuse les saisies incorrectes d’une résidence', function (array $surcharge, string $champ): void {
    gestionnaireConnecte();
    $base = [
        'proprietaire_id' => Proprietaire::factory()->create()->id,
        'quartier_id' => ResidenceFactory::unQuartier()->id, 'nom' => 'Résidence Test',
    ];

    test()->postJson('/api/v1/backoffice/residences', [...$base, ...$surcharge])
        ->assertStatus(422)->assertJsonStructure(['errors' => [$champ]]);
})->with([
    'propriétaire inconnu' => [['proprietaire_id' => 9999], 'proprietaire_id'],
    'quartier inconnu' => [['quartier_id' => 9999], 'quartier_id'],
    'nom trop court' => [['nom' => 'AB'], 'nom'],
    'latitude sans longitude' => [['latitude' => 5.35], 'longitude'],
    'latitude impossible' => [['latitude' => 120, 'longitude' => -4], 'latitude'],
    'mode de vente inconnu' => [['mode_vente' => 'enchere'], 'mode_vente'],
]);

it('filtre les résidences par commune et par nom', function (): void {
    $auteur = gestionnaireConnecte();
    $angre = Residence::factory()->create(['nom' => 'Résidence Angré Star', 'quartier_id' => ResidenceFactory::unQuartier('Cocody', 'Angré')->id]);
    $zone4 = Residence::factory()->create(['nom' => 'Résidence Zone 4', 'quartier_id' => ResidenceFactory::unQuartier('Marcory', 'Zone 4')->id]);
    visiblePar($auteur, $angre, $zone4);
    $cocody = ResidenceFactory::unQuartier('Cocody', 'Angré')->commune_id;

    $parCommune = test()->getJson("/api/v1/backoffice/residences?commune_id={$cocody}")->assertOk();
    expect(array_column($parCommune->json('data.elements'), 'nom'))->toBe(['Résidence Angré Star']);

    $parNom = test()->getJson('/api/v1/backoffice/residences?recherche=zone')->assertOk();
    expect(array_column($parNom->json('data.elements'), 'nom'))->toBe(['Résidence Zone 4']);
});

it('ne supprime pas une résidence qui contient des logements', function (): void {
    $auteur = gestionnaireConnecte();
    $pleine = Logement::factory()->create()->residence;
    $vide = Residence::factory()->create();
    visiblePar($auteur, $pleine, $vide);

    test()->deleteJson("/api/v1/backoffice/residences/{$pleine->id}")
        ->assertStatus(409)->assertJsonPath('errors.code.0', 'residence_non_vide');
    test()->deleteJson("/api/v1/backoffice/residences/{$vide->id}")->assertOk();
});

// ---------------------------------------------------------------- logements

it('crée un logement en brouillon, avec sa référence et son résumé de vignette', function (): void {
    $auteur = gestionnaireConnecte();
    $residence = Residence::factory()->create();
    visiblePar($auteur, $residence);
    $wifi = Equipement::create(['nom' => 'Wifi', 'portee' => 'logement']);

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$residence->id}/logements", saisieLogement(['equipements' => [$wifi->id]]))
        ->assertCreated();

    expect($reponse->json('data.reference'))->toMatch('/^LOG-\d{5}$/')
        ->and($reponse->json('data.resume'))->toBe('Appartement 3 pièces, 2 chambres')
        ->and($reponse->json('data.etat_publication'))->toBe('brouillon')
        ->and($reponse->json('data.equipements.0.nom'))->toBe('Wifi')
        // horaires et durée minimale hérités des Paramètres tant que le logement n'a pas les siens
        ->and($reponse->json('data.heure_arrivee'))->toBe('14:00')
        ->and($reponse->json('data.heure_depart'))->toBe('12:00')
        ->and($reponse->json('data.duree_minimale'))->toBe(1);
});

it('ne laisse changer ni le prix ni l’état de publication par la fiche du logement', function (): void {
    $auteur = gestionnaireConnecte();
    $logement = Logement::factory()->create();
    visiblePar($auteur, $logement);

    test()->putJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}", [
        'nom' => 'Nouveau nom', 'prix_vente' => 1, 'prix_proprietaire' => 1, 'etat_publication' => 'publie',
    ])->assertOk();

    $logement->refresh();
    expect($logement->nom)->toBe('Nouveau nom')
        ->and($logement->prix_vente)->toBeNull()
        ->and($logement->prix_proprietaire)->toBeNull()
        ->and($logement->etat_publication)->toBe(EtatPublication::Brouillon);
});

it('refuse les saisies incorrectes d’un logement', function (array $surcharge, string $champ): void {
    $auteur = gestionnaireConnecte();
    $residence = Residence::factory()->create();
    visiblePar($auteur, $residence);

    test()->postJson("/api/v1/backoffice/residences/{$residence->id}/logements", saisieLogement($surcharge))
        ->assertStatus(422)->assertJsonStructure(['errors' => [$champ]]);
})->with([
    'sans type : il ne pourrait pas être chiffré' => [['type_logement_id' => null], 'type_logement_id'],
    'capacité maximale sous la capacité de base' => [['capacite_de_base' => 4, 'capacite_maximale' => 2], 'capacite_maximale'],
    'plus de chambres que de pièces' => [['nombre_pieces' => 2, 'nombre_chambres' => 3], 'nombre_chambres'],
    'durée maximale sous la durée minimale' => [['duree_minimale' => 7, 'duree_maximale' => 3], 'duree_maximale'],
    'caution négative' => [['caution' => -1], 'caution'],
    'heure mal formée' => [['heure_arrivee' => '14h'], 'heure_arrivee'],
    'politique inconnue' => [['politique_annulation' => 'souple'], 'politique_annulation'],
]);

it('ne trouve un logement que dans sa propre résidence', function (): void {
    $auteur = gestionnaireConnecte();
    $logement = Logement::factory()->create();
    $autre = Residence::factory()->create();
    visiblePar($auteur, $logement, $autre);

    test()->getJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}")->assertOk();
    test()->getJson("/api/v1/backoffice/residences/{$autre->id}/logements/{$logement->id}")->assertNotFound();
});

it('garde un logement supprimé pour l’historique des séjours', function (): void {
    $auteur = gestionnaireConnecte();
    $logement = Logement::factory()->create();
    visiblePar($auteur, $logement);

    test()->deleteJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}")->assertOk();

    expect(Logement::find($logement->id))->toBeNull()
        ->and(Logement::withTrashed()->find($logement->id))->not->toBeNull();
});

it('fait garantir par la base la cohérence des capacités, quoi que fasse l’application', function (): void {
    $logement = Logement::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('logements')->where('id', $logement->id)->update(['capacite_maximale' => 1, 'capacite_de_base' => 5])))
        ->toThrow(QueryException::class);
});

it('montre la résidence complète avec ses logements', function (): void {
    $auteur = gestionnaireConnecte();
    $residence = Residence::factory()->create();
    visiblePar($auteur, $residence);
    Logement::factory()->count(2)->create(['residence_id' => $residence->id]);

    $reponse = test()->getJson("/api/v1/backoffice/residences/{$residence->id}")->assertOk();

    expect($reponse->json('data.nombre_logements'))->toBe(2)
        ->and($reponse->json('data.logements'))->toHaveCount(2)
        ->and($reponse->json('data.proprietaire.nom'))->not->toBeEmpty();
});

// ---------------------------------------------------------------- export (CdC § 6.8)

it('exporte la liste des résidences en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    $auteur = gestionnaireConnecte(Profil::Administrateur);
    Residence::factory()->create();

    $reponse = test()->getJson('/api/v1/backoffice/residences/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('n’exporte que les résidences visibles par un gestionnaire cloisonné', function (): void {
    $gestionnaire = gestionnaireConnecte();
    $visible = Residence::factory()->create();
    Residence::factory()->create(); // pas confiée : ne doit jamais apparaître
    visiblePar($gestionnaire, $visible);

    $reponse = test()->getJson('/api/v1/backoffice/residences/export?format=xlsx')->assertOk();

    expect(nombreDeLignesDuClasseurResidences((string) $reponse->getContent()))->toBe(1);
});

/** Lit le classeur reçu en réponse et compte ses lignes de données (hors en-tête). */
function nombreDeLignesDuClasseurResidences(string $contenu): int
{
    $chemin = tempnam(sys_get_temp_dir(), 'test-export-residences-');
    file_put_contents($chemin, $contenu);

    try {
        $feuille = IOFactory::load($chemin)->getActiveSheet();

        return max($feuille->getHighestRow() - 1, 0);
    } finally {
        unlink($chemin);
    }
}
