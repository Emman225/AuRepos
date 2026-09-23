<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Services\Missions;
use App\Domain\Sejours\Services\Calendrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
});

it('surface les missions de ménage de la période dans « missions », sans casser la grille existante', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $mission = app(Missions::class)->demander($this->logement, 'Ménage demandé par le back office', $auteur, Carbon::parse('2026-09-15'));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $missions = $reponse->json('data.missions');
    expect($missions)->toHaveCount(1);
    expect($missions[0])
        ->id->toBe($mission->id)
        ->logement_id->toBe($this->logement->id)
        ->type->toBe('menage')
        ->statut->toBe('a_faire')
        ->echeance->toBe('2026-09-15');
});

it('n’affiche pas une mission de ménage hors de la période demandée', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    app(Missions::class)->demander($this->logement, 'Ménage', $auteur, Carbon::parse('2026-01-05'));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.missions'))->toBe([]);
});

it('surface un blocage « maintenance » et un blocage « usage du propriétaire » qui chevauchent la période dans « blocages »', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $calendrier = app(Calendrier::class);
    $maintenance = $calendrier->bloquer($this->logement, Carbon::parse('2026-09-10'), Carbon::parse('2026-09-12'), 'maintenance', null, $auteur);
    $proprietaire = $calendrier->bloquer($this->logement, Carbon::parse('2026-09-20'), Carbon::parse('2026-09-22'), 'usage_proprietaire', null, $auteur);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $blocages = collect($reponse->json('data.blocages'));
    expect($blocages)->toHaveCount(2);
    expect($blocages->firstWhere('id', $maintenance->id))
        ->motif->toBe('maintenance')
        ->debut->toBe('2026-09-10')
        ->fin->toBe('2026-09-12');
    expect($blocages->firstWhere('id', $proprietaire->id))->motif->toBe('usage_proprietaire');
});

it('n’affiche pas un blocage hors de la période demandée', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    app(Calendrier::class)->bloquer($this->logement, Carbon::parse('2026-01-05'), Carbon::parse('2026-01-08'), 'maintenance', null, $auteur);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.blocages'))->toBe([]);
});

it('marque un logement « ferme » quand sa résidence est en état « Occupée » (bouton propriétaire)', function (): void {
    $this->residence->forceFill(['disponibilite' => Disponibilite::Occupee])->save();

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $logement = collect($reponse->json('data.logements'))->firstWhere('id', $this->logement->id);
    expect($logement['ferme'])->toBeTrue();
});

it('ne marque pas un logement « ferme » quand sa résidence est disponible (par défaut)', function (): void {
    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $logement = collect($reponse->json('data.logements'))->firstWhere('id', $this->logement->id);
    expect($logement['ferme'])->toBeFalse();
});

it('reste défensif quand aucune mission ni aucun blocage n’existe encore : listes vides, aucune erreur', function (): void {
    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.missions'))->toBe([]);
    expect($reponse->json('data.blocages'))->toBe([]);
});

it('reste défensif quand il n’y a aucun logement dans le périmètre : listes vides, aucune erreur', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.logements'))->toBe([]);
    expect($reponse->json('data.missions'))->toBe([]);
    expect($reponse->json('data.blocages'))->toBe([]);
    expect($reponse->json('data.indicateurs.nuits_disponibles'))->toBe(0);
});
