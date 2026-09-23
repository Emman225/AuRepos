<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\Quartier;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Referentiels\Models\Ville;
use Database\Seeders\ReferentielsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function auBackOffice(Profil $profil = Profil::Gestionnaire): User
{
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

function abidjan(): Ville
{
    $region = Region::create(['nom' => 'District autonome d’Abidjan']);

    return Ville::create(['region_id' => $region->id, 'nom' => 'Abidjan']);
}

// ---------------------------------------------------------------- données de départ

it('installe Abidjan, ses communes, ses quartiers, les types et les équipements', function (): void {
    $this->seed(ReferentielsSeeder::class);

    $cocody = Commune::firstWhere('nom', 'Cocody');
    expect(Commune::count())->toBe(13)
        ->and($cocody->quartiers()->pluck('nom'))->toContain('Angré', 'II Plateaux', 'Riviera Palmeraie')
        ->and(TypeLogement::pluck('code')->all())->toContain('studio', 'f2', 'f3', 'villa', 'chambre')
        ->and(Equipement::where('filtre_recherche', true)->pluck('nom')->all())
        ->toEqualCanonicalizing(['Wifi', 'Climatisation', 'Piscine', 'Parking', 'Groupe électrogène', 'Gardiennage']);
});

it('se relance sans créer de doublon ni écraser une modification', function (): void {
    $this->seed(ReferentielsSeeder::class);
    $avant = [Commune::count(), Quartier::count(), TypeLogement::count(), Equipement::count()];
    TypeLogement::firstWhere('code', 'studio')->update(['nom' => 'Studio meublé']);

    $this->seed(ReferentielsSeeder::class);

    expect([Commune::count(), Quartier::count(), TypeLogement::count(), Equipement::count()])->toBe($avant)
        ->and(TypeLogement::firstWhere('code', 'studio')->nom)->toBe('Studio meublé');
});

// ---------------------------------------------------------------- listes de choix publiques

it('sert la cascade commune › quartier sans connexion, éléments actifs seulement', function (): void {
    $ville = abidjan();
    $cocody = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $marcory = Commune::create(['ville_id' => $ville->id, 'nom' => 'Marcory']);
    Commune::create(['ville_id' => $ville->id, 'nom' => 'Ancienne commune', 'actif' => false]);
    Quartier::create(['commune_id' => $cocody->id, 'nom' => 'Riviera']);
    Quartier::create(['commune_id' => $cocody->id, 'nom' => 'Angré']);
    Quartier::create(['commune_id' => $marcory->id, 'nom' => 'Zone 4']);

    $communes = test()->getJson("/api/v1/referentiels/communes?ville_id={$ville->id}")->assertOk()->json('data');
    expect(array_column($communes, 'nom'))->toBe(['Cocody', 'Marcory']);

    $quartiers = test()->getJson("/api/v1/referentiels/quartiers?commune_id={$cocody->id}")->assertOk()->json('data');
    expect(array_column($quartiers, 'nom'))->toBe(['Angré', 'Riviera']);
});

it('ne montre jamais au public les agences ni les statuts métier', function (string $slug): void {
    test()->getJson("/api/v1/referentiels/{$slug}")->assertNotFound();
})->with(['agences', 'statuts-metier', 'inexistant']);

// ---------------------------------------------------------------- gestion au back office

it('réserve la gestion au personnel d’exploitation', function (Profil $profil, int $statut): void {
    auBackOffice($profil);

    test()->getJson('/api/v1/backoffice/referentiels/communes')->assertStatus($statut);
})->with([
    [Profil::SuperAdministrateur, 200], [Profil::Administrateur, 200], [Profil::Gestionnaire, 200],
    [Profil::Gouvernante, 403], [Profil::Proprietaire, 403], [Profil::Client, 403],
]);

it('réserve les agences et les statuts métier aux administrateurs', function (): void {
    auBackOffice(Profil::Gestionnaire);
    test()->getJson('/api/v1/backoffice/referentiels/agences')->assertForbidden();
    test()->postJson('/api/v1/backoffice/referentiels/agences', ['nom' => 'Agence pirate'])->assertForbidden();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    auBackOffice(Profil::Administrateur);
    test()->postJson('/api/v1/backoffice/referentiels/agences', ['nom' => 'Agence Plateau'])->assertCreated();
});

it('crée, modifie et trace un élément', function (): void {
    $gestionnaire = auBackOffice();
    $ville = abidjan();

    $id = test()->postJson('/api/v1/backoffice/referentiels/communes', ['ville_id' => $ville->id, 'nom' => 'Cocody'])
        ->assertCreated()->json('data.id');

    test()->putJson("/api/v1/backoffice/referentiels/communes/{$id}", ['actif' => false])
        ->assertOk()->assertJsonPath('data.actif', false)->assertJsonPath('data.nom', 'Cocody');

    $traces = EntreeAudit::where('sujet_type', 'Commune')->where('user_id', $gestionnaire->id)->pluck('recit')->all();
    expect($traces)->toContain('Création : commune « Cocody ».')
        ->and(collect($traces)->contains(fn ($r) => str_contains($r, 'Modification') && str_contains($r, 'actif')))->toBeTrue();
});

it('juge l’unicité d’un nom dans son parent', function (): void {
    auBackOffice();
    $ville = abidjan();
    $cocody = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $plateau = Commune::create(['ville_id' => $ville->id, 'nom' => 'Plateau']);
    Quartier::create(['commune_id' => $cocody->id, 'nom' => 'Centre']);

    // Le même nom dans une AUTRE commune est permis…
    test()->postJson('/api/v1/backoffice/referentiels/quartiers', ['commune_id' => $plateau->id, 'nom' => 'Centre'])->assertCreated();
    // …mais pas deux fois dans la même.
    test()->postJson('/api/v1/backoffice/referentiels/quartiers', ['commune_id' => $cocody->id, 'nom' => 'Centre'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['nom']]);
});

it('refuse les saisies incorrectes', function (string $slug, array $saisie, string $champ): void {
    auBackOffice(Profil::Administrateur);

    test()->postJson("/api/v1/backoffice/referentiels/{$slug}", $saisie)
        ->assertStatus(422)->assertJsonStructure(['errors' => [$champ]]);
})->with([
    'commune sans ville' => ['communes', ['nom' => 'Sans ville'], 'ville_id'],
    'commune d’une ville inconnue' => ['communes', ['ville_id' => 999, 'nom' => 'X'], 'ville_id'],
    'type sans code' => ['types-logement', ['nom' => 'Loft'], 'code'],
    'code de type mal formé' => ['types-logement', ['code' => 'Loft Chic', 'nom' => 'Loft'], 'code'],
    'portée d’équipement inconnue' => ['equipements', ['nom' => 'Jacuzzi', 'portee' => 'immeuble'], 'portee'],
    'couleur mal formée' => ['statuts-metier', ['domaine' => 'sejour', 'code' => 'confirme', 'libelle' => 'Confirmé', 'couleur' => 'bleu'], 'couleur'],
]);

it('refuse de supprimer un élément utilisé et propose de le désactiver', function (): void {
    auBackOffice();
    $ville = abidjan();
    $cocody = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $libre = Commune::create(['ville_id' => $ville->id, 'nom' => 'Commune sans quartier']);
    Quartier::create(['commune_id' => $cocody->id, 'nom' => 'Angré']);

    test()->deleteJson("/api/v1/backoffice/referentiels/communes/{$cocody->id}")
        ->assertStatus(409)
        ->assertJsonPath('errors.code.0', 'element_utilise');
    expect(Commune::whereKey($cocody->id)->exists())->toBeTrue();

    test()->deleteJson("/api/v1/backoffice/referentiels/communes/{$libre->id}")->assertOk();
    expect(Commune::whereKey($libre->id)->exists())->toBeFalse();
});

it('exporte un référentiel en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    auBackOffice();
    $ville = abidjan();
    Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);

    $reponse = test()->getJson('/api/v1/backoffice/referentiels/communes/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /referentiels/{slug}/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    auBackOffice();

    test()->getJson('/api/v1/backoffice/referentiels/communes/export?format=xlsx')->assertOk();
});

it('cherche et pagine la liste du back office, avec le nom du parent', function (): void {
    auBackOffice();
    $ville = abidjan();
    foreach (['Cocody', 'Marcory', 'Koumassi', 'Yopougon'] as $nom) {
        Commune::create(['ville_id' => $ville->id, 'nom' => $nom]);
    }

    $reponse = test()->getJson('/api/v1/backoffice/referentiels/communes?recherche=co&par_page=5')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'nom'))->toBe(['Cocody', 'Marcory'])
        ->and($reponse->json('data.elements.0.parent'))->toBe('Abidjan')
        ->and($reponse->json('data.pagination.total'))->toBe(2);
});
