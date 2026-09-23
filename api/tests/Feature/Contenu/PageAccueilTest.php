<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Banniere;
use App\Domain\Contenu\Models\Diapositive;
use App\Domain\Contenu\Models\Temoignage;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function gestionnaireDuCatalogueConnecte(Profil $profil = Profil::Gestionnaire): User
{
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- mises en avant

it('ne met en avant que les logements publiés et mis en avant', function (): void {
    $publieEtMisEnAvant = Logement::factory()->create(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 45000, 'mise_en_avant' => true]);
    Logement::factory()->create(['etat_publication' => EtatPublication::Publie, 'mise_en_avant' => false]); // pas mis en avant
    Logement::factory()->create(['etat_publication' => EtatPublication::Brouillon, 'mise_en_avant' => true]); // pas publié

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    expect($reponse->json('data.mises_en_avant'))->toHaveCount(1)
        ->and($reponse->json('data.mises_en_avant.0.reference'))->toBe($publieEtMisEnAvant->reference)
        ->and($reponse->json('data.mises_en_avant.0.prix_par_nuit'))->toBe(45000);
});

it('ne met jamais en avant un logement d’une résidence désactivée', function (): void {
    $logement = Logement::factory()->create(['etat_publication' => EtatPublication::Publie, 'mise_en_avant' => true]);
    $logement->residence()->update(['active' => false]);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    expect($reponse->json('data.mises_en_avant'))->toHaveCount(0);
});

// ---------------------------------------------------------------- bannières et témoignages

it('ne renvoie que les bannières actives, dans l’ordre demandé', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Banniere::create(['titre' => 'Seconde', 'image_url' => 'https://exemple.ci/b2.jpg', 'ordre' => 2, 'actif' => true, 'cree_par' => $auteur->id]);
    Banniere::create(['titre' => 'Première', 'image_url' => 'https://exemple.ci/b1.jpg', 'ordre' => 1, 'actif' => true, 'cree_par' => $auteur->id]);
    Banniere::create(['titre' => 'Retirée', 'image_url' => 'https://exemple.ci/b3.jpg', 'ordre' => 0, 'actif' => false, 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    expect($reponse->json('data.bannieres'))->toHaveCount(2)
        ->and($reponse->json('data.bannieres.0.titre'))->toBe('Première')
        ->and($reponse->json('data.bannieres.1.titre'))->toBe('Seconde');
});

it('ne renvoie que les témoignages publiés', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Temoignage::create(['nom_client' => 'Aya K.', 'message' => 'Séjour parfait.', 'note' => 5, 'publie' => true, 'cree_par' => $auteur->id]);
    Temoignage::create(['nom_client' => 'Brouillon', 'message' => 'Pas encore validé.', 'publie' => false, 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    expect($reponse->json('data.temoignages'))->toHaveCount(1)
        ->and($reponse->json('data.temoignages.0.nom_client'))->toBe('Aya K.');
});

it('refuse une note de témoignage hors de 1 à 5', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();

    expect(fn () => Temoignage::create(['nom_client' => 'X', 'message' => 'Y', 'note' => 6, 'cree_par' => $auteur->id]))
        ->toThrow(QueryException::class);
});

it('ne renvoie que les diapositives actives du carrousel, dans l’ordre demandé, distinctes des bannières', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Diapositive::create(['image_url' => 'https://exemple.ci/c2.jpg', 'ordre' => 2, 'actif' => true, 'cree_par' => $auteur->id]);
    Diapositive::create(['image_url' => 'https://exemple.ci/c1.jpg', 'ordre' => 1, 'actif' => true, 'cree_par' => $auteur->id]);
    Diapositive::create(['image_url' => 'https://exemple.ci/c3.jpg', 'ordre' => 0, 'actif' => false, 'cree_par' => $auteur->id]);
    Banniere::create(['titre' => 'Promo', 'image_url' => 'https://exemple.ci/b.jpg', 'actif' => true, 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    expect($reponse->json('data.carrousel'))->toHaveCount(2)
        ->and($reponse->json('data.carrousel.0.image'))->toBe('https://exemple.ci/c1.jpg')
        ->and($reponse->json('data.carrousel.1.image'))->toBe('https://exemple.ci/c2.jpg')
        ->and($reponse->json('data.bannieres'))->toHaveCount(1);
});

// ---------------------------------------------------------------- résidences

it('ne met en avant que les résidences actives et marquées, avec leur prix de départ', function (): void {
    $active = Logement::factory()->create(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000]);
    $active->residence()->update(['mise_en_avant' => true]);
    $nonMarquee = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $inactive = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $inactive->residence()->update(['mise_en_avant' => true, 'active' => false]);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    $noms = array_column($reponse->json('data.residences_mises_en_avant'), 'nom');
    expect($noms)->toContain($active->residence->nom)
        ->and($noms)->not->toContain($nonMarquee->residence->nom)
        ->and($noms)->not->toContain($inactive->residence->nom)
        ->and($reponse->json('data.residences_mises_en_avant.0.a_partir_de'))->toBe(30000);
});

it('classe les résidences les mieux notées par moyenne des avis PUBLIÉS, jamais ceux en attente ou refusés', function (): void {
    $bienNotee = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $malNotee = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $sansAvis = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);

    $client = User::factory()->create();
    foreach ([[$bienNotee, 5], [$malNotee, 2]] as [$logement, $note]) {
        $sejour = Sejour::factory()->create([
            'logement_id' => $logement->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
        ]);
        Avis::create(['sejour_id' => $sejour->id, 'note' => $note, 'statut' => 'publie']);
    }
    // Un avis en attente ne doit JAMAIS entrer dans la moyenne publique.
    $sejourEnAttente = Sejour::factory()->create([
        'logement_id' => $sansAvis->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
    ]);
    Avis::create(['sejour_id' => $sejourEnAttente->id, 'note' => 5, 'statut' => 'en_attente']);

    $reponse = test()->getJson('/api/v1/accueil')->assertOk();

    $residences = $reponse->json('data.residences_mieux_notees');
    $noms = array_column($residences, 'nom');
    expect($noms)->toContain($bienNotee->residence->nom, $malNotee->residence->nom)
        ->and($noms)->not->toContain($sansAvis->residence->nom);

    $parNom = collect($residences)->keyBy('nom');
    expect((float) $parNom[$bienNotee->residence->nom]['note_moyenne'])->toBe(5.0)
        ->and((float) $parNom[$malNotee->residence->nom]['note_moyenne'])->toBe(2.0);
});

// ---------------------------------------------------------------- bascule « mise en avant »

it('permet au gestionnaire de mettre un logement en avant depuis la fiche existante', function (): void {
    $gestionnaire = gestionnaireDuCatalogueConnecte();
    $logement = Logement::factory()->create();
    $gestionnaire->residences()->attach($logement->residence_id);

    test()->putJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}", ['mise_en_avant' => true])
        ->assertOk()->assertJsonPath('data.mise_en_avant', true);

    expect($logement->refresh()->mise_en_avant)->toBeTrue();
});

it('cache la page d’accueil quand le site est en construction, comme le reste du catalogue public', function (): void {
    app(Parametres::class)->enregistrer('general', ['site_en_construction' => true], User::factory()->profil(Profil::SuperAdministrateur)->create());

    test()->getJson('/api/v1/accueil')->assertStatus(503);
});
