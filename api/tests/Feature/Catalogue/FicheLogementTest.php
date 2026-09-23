<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Calendrier;
use App\Domain\Tarification\Models\LigneDeGrille;
use App\Domain\Tarification\Models\Saison;
use App\Domain\Tarification\Models\TrancheDuree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function publierAvecPrix(int $prixVente = 30000): Logement
{
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => $prixVente])->save();

    return $logement->refresh();
}

// ---------------------------------------------------------------- calendrier de disponibilité

it('rend les nuits occupées d’un mois, jamais celles d’un autre logement', function (): void {
    $logement = publierAvecPrix();
    $autre = publierAvecPrix();
    Carbon::setTestNow('2026-10-01');

    $sejour = Sejour::factory()->for($logement)->create(['arrivee' => '2026-11-10', 'depart' => '2026-11-13']);
    app(Calendrier::class)->occuper($sejour->refresh());
    $sejourAutreLogement = Sejour::factory()->for($autre)->create(['arrivee' => '2026-11-10', 'depart' => '2026-11-11']);
    app(Calendrier::class)->occuper($sejourAutreLogement->refresh());

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$logement->reference}/disponibilite?mois=2026-11")->assertOk();

    expect($reponse->json('data.jours_occupes'))->toBe(['2026-11-10', '2026-11-11', '2026-11-12']);
});

it('refuse un mois mal formé', function (): void {
    $logement = publierAvecPrix();

    test()->getJson("/api/v1/catalogue/logements/{$logement->reference}/disponibilite?mois=novembre")->assertStatus(422);
});

// ---------------------------------------------------------------- tarif par saison

it('affiche un tarif indicatif par saison, sur la tranche la plus courte', function (): void {
    $logement = publierAvecPrix(30000);
    $basse = Saison::create(['nom' => 'Basse saison', 'categorie' => 'basse', 'date_debut' => '2026-01-01', 'date_fin' => '2026-06-30']);
    $haute = Saison::create(['nom' => 'Haute saison', 'categorie' => 'haute', 'date_debut' => '2026-07-01', 'date_fin' => '2026-12-31']);
    $courte = TrancheDuree::create(['nom' => '1 à 6 nuits', 'nuits_min' => 1, 'nuits_max' => 6]);
    TrancheDuree::create(['nom' => '7 nuits et plus', 'nuits_min' => 7, 'nuits_max' => null]);
    LigneDeGrille::create(['type_logement_id' => $logement->type_logement_id, 'saison_id' => $haute->id, 'tranche_duree_id' => $courte->id, 'tarif' => 45000]);
    // Basse saison : aucune ligne de grille → repli sur le prix de vente.

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$logement->reference}")->assertOk();
    $parSaison = collect($reponse->json('data.tarifs_par_saison'))->keyBy('saison');

    expect($parSaison['Haute saison']['tarif'])->toBe(45000)
        ->and($parSaison['Basse saison']['tarif'])->toBe(30000)
        ->and($parSaison)->toHaveCount(2); // les événements n'y figurent pas
});

it('n’expose aucun tarif de saison sans grille ni prix de vente', function (): void {
    $logement = Logement::factory()->create(['prix_vente' => null]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie])->save();
    Saison::create(['nom' => 'Basse saison', 'categorie' => 'basse', 'date_debut' => '2026-01-01', 'date_fin' => '2026-12-31']);
    TrancheDuree::create(['nom' => '1 à 6 nuits', 'nuits_min' => 1, 'nuits_max' => 6]);

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$logement->refresh()->reference}")->assertOk();

    expect($reponse->json('data.tarifs_par_saison'))->toBe([]);
});

// ---------------------------------------------------------------- logements similaires et avis

it('propose des logements similaires du même type, jamais lui-même', function (): void {
    $type = TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3]);
    $villa = TypeLogement::firstOrCreate(['code' => 'villa'], ['nom' => 'Villa', 'nombre_pieces' => 6]);
    $logement = publierAvecPrix();
    $logement->forceFill(['type_logement_id' => $type->id])->save();
    $semblable = publierAvecPrix();
    $semblable->forceFill(['type_logement_id' => $type->id])->save();
    $autreType = publierAvecPrix();
    $autreType->forceFill(['type_logement_id' => $villa->id])->save(); // type différent : ne doit pas apparaître

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$logement->refresh()->reference}")->assertOk();
    $references = collect($reponse->json('data.similaires'))->pluck('reference');

    expect($references)->toContain($semblable->refresh()->reference)
        ->not->toContain($logement->reference)
        ->not->toContain($autreType->refresh()->reference);
});

it('rend un tableau d’avis vide en attendant P2-AVI-01, jamais un champ absent', function (): void {
    $logement = publierAvecPrix();

    test()->getJson("/api/v1/catalogue/logements/{$logement->reference}")
        ->assertOk()->assertJsonPath('data.avis', []);
});

it('cache la disponibilité comme le reste du catalogue public quand le site est en construction', function (): void {
    $logement = publierAvecPrix();
    app(Parametres::class)->enregistrer('general', ['site_en_construction' => true], User::factory()->profil(Profil::SuperAdministrateur)->create());

    test()->getJson("/api/v1/catalogue/logements/{$logement->reference}/disponibilite?mois=2026-11")->assertStatus(503);
});
