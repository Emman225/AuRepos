<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Models\LigneDeGrille;
use App\Domain\Tarification\Models\Saison;
use App\Domain\Tarification\Models\TrancheDuree;
use App\Domain\Tarification\Services\Tarifs;
use App\Domain\Tarification\Services\VerificationDeLaGrille;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Date figée : les saisons du jeu d'essai restent « à venir » quel que soit le jour où tournent les tests.
    Carbon::setTestNow('2026-10-01 10:00:00');
});

function administrateur(Profil $profil = Profil::Administrateur): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $u = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($u));

    return $u;
}

/** Une grille saine : deux saisons qui se suivent, un événement par-dessus, trois tranches jointives. */
function grilleSaine(): array
{
    return [
        'basse' => Saison::create(['nom' => 'Basse saison', 'categorie' => 'basse', 'date_debut' => '2026-10-01', 'date_fin' => '2026-12-14']),
        'haute' => Saison::create(['nom' => 'Haute saison', 'categorie' => 'haute', 'date_debut' => '2026-12-15', 'date_fin' => '2027-10-31']),
        'fetes' => Saison::create(['nom' => 'Fêtes de fin d’année', 'categorie' => 'evenement', 'date_debut' => '2026-12-24', 'date_fin' => '2027-01-02']),
        'courte' => TrancheDuree::create(['nom' => '1 à 6 nuits', 'nuits_min' => 1, 'nuits_max' => 6]),
        'semaine' => TrancheDuree::create(['nom' => '7 à 29 nuits', 'nuits_min' => 7, 'nuits_max' => 29]),
        'longue' => TrancheDuree::create(['nom' => '30 nuits et plus', 'nuits_min' => 30, 'nuits_max' => null]),
    ];
}

function tarifer(array $g, array $cible, array $tarifs): void
{
    foreach ($tarifs as [$saison, $tranche, $tarif]) {
        LigneDeGrille::create([...$cible, 'saison_id' => $g[$saison]->id, 'tranche_duree_id' => $g[$tranche]->id, 'tarif' => $tarif]);
    }
}

function codes(): array
{
    return array_column(app(VerificationDeLaGrille::class)->anomalies(), 'code');
}

// ---------------------------------------------------------------- lecture du tarif

it('lit le tarif du plus précis au plus général : logement, puis type, puis prix de vente', function (): void {
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    $logement->forceFill(['prix_vente' => 30000])->save();
    $tarifs = fn () => app(Tarifs::class)->nuitees($logement->refresh(), Carbon::parse('2026-10-05'), Carbon::parse('2026-10-06'))[0];

    expect($tarifs())->toMatchArray(['tarif' => 30000, 'origine' => 'prix de vente du logement']);

    tarifer($g, ['type_logement_id' => $logement->type_logement_id], [['basse', 'courte', 28000]]);
    expect($tarifs())->toMatchArray(['tarif' => 28000, 'origine' => 'grille du type', 'saison' => 'Basse saison']);

    tarifer($g, ['logement_id' => $logement->id], [['basse', 'courte', 33000]]);
    expect($tarifs())->toMatchArray(['tarif' => 33000, 'origine' => 'grille du logement']);
});

it('juge la saison nuit par nuit et la durée sur le séjour entier', function (): void {
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    tarifer($g, ['type_logement_id' => $logement->type_logement_id], [
        ['basse', 'courte', 25000], ['haute', 'courte', 35000], ['basse', 'semaine', 22000], ['haute', 'semaine', 31000],
    ]);

    // 4 nuits à cheval : 13 et 14 décembre en basse saison, 15 et 16 en haute.
    $courtes = app(Tarifs::class)->nuitees($logement, Carbon::parse('2026-12-13'), Carbon::parse('2026-12-17'));
    expect(array_column($courtes, 'tarif'))->toBe([25000, 25000, 35000, 35000]);

    // 8 nuits : tout le séjour passe dans la tranche « 7 à 29 nuits ».
    $longues = app(Tarifs::class)->nuitees($logement, Carbon::parse('2026-12-10'), Carbon::parse('2026-12-18'));
    expect(array_column($longues, 'tarif'))->toBe([22000, 22000, 22000, 22000, 22000, 31000, 31000, 31000]);
});

it('fait passer un événement avant la saison qu’il recouvre', function (): void {
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    tarifer($g, ['type_logement_id' => $logement->type_logement_id], [['haute', 'courte', 35000], ['fetes', 'courte', 60000]]);

    $nuits = app(Tarifs::class)->nuitees($logement, Carbon::parse('2026-12-23'), Carbon::parse('2026-12-25'));

    expect(array_column($nuits, 'tarif'))->toBe([35000, 60000])
        ->and($nuits[1]['saison'])->toBe('Fêtes de fin d’année');
});

it('refuse de chiffrer une nuit qui n’a aucun tarif', function (): void {
    $logement = Logement::factory()->create(); // ni grille, ni prix de vente

    expect(fn () => app(Tarifs::class)->nuitees($logement, Carbon::parse('2026-10-05'), Carbon::parse('2026-10-07')))
        ->toThrow(ErreurMetier::class, 'Aucun tarif');
});

// ---------------------------------------------------------------- vérification automatique

it('est muette sur une grille saine et complète', function (): void {
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    foreach (['basse', 'haute', 'fetes'] as $s) {
        foreach (['courte', 'semaine', 'longue'] as $t) {
            tarifer($g, ['type_logement_id' => $logement->type_logement_id], [[$s, $t, 30000]]);
        }
    }

    expect(app(VerificationDeLaGrille::class)->estMuette())->toBeTrue()
        ->and(codes())->toBe([]);
});

it('signale un chevauchement de saisons, mais pas un événement posé par-dessus', function (): void {
    grilleSaine();
    expect(codes())->not->toContain('chevauchement_de_saisons');

    Saison::create(['nom' => 'Haute bis', 'categorie' => 'haute', 'date_debut' => '2026-12-01', 'date_fin' => '2026-12-20']);
    expect(codes())->toContain('chevauchement_de_saisons');
});

it('signale un trou de dates entre deux saisons', function (): void {
    Saison::create(['nom' => 'Automne', 'categorie' => 'basse', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-31']);
    Saison::create(['nom' => 'Hiver', 'categorie' => 'haute', 'date_debut' => '2026-11-15', 'date_fin' => '2027-10-31']);

    $messages = implode(' ', array_column(app(VerificationDeLaGrille::class)->anomalies(), 'message'));

    expect($messages)->toContain('01/11/2026')->toContain('14/11/2026');
});

it('signale les trous et les chevauchements de durée', function (array $tranches, string $code): void {
    foreach ($tranches as [$min, $max]) {
        TrancheDuree::create(['nom' => "{$min}-{$max}", 'nuits_min' => $min, 'nuits_max' => $max]);
    }

    expect(codes())->toContain($code);
})->with([
    'ne commence pas à 1 nuit' => [[[2, 6], [7, null]], 'trou_de_duree'],
    'trou entre deux tranches' => [[[1, 6], [10, null]], 'trou_de_duree'],
    'dernière tranche fermée' => [[[1, 6], [7, 29]], 'trou_de_duree'],
    'chevauchement' => [[[1, 7], [7, null]], 'chevauchement_de_durees'],
]);

it('bloque sur un type non tarifé dont un logement n’a pas de prix de vente, et informe seulement s’il en a un', function (): void {
    grilleSaine();
    $logement = Logement::factory()->create();

    expect(codes())->toContain('type_non_tarife')
        ->and(app(VerificationDeLaGrille::class)->estMuette())->toBeFalse();

    $logement->forceFill(['prix_vente' => 30000])->save();

    expect(codes())->not->toContain('type_non_tarife')->toContain('repli_sur_prix_de_vente')
        ->and(app(VerificationDeLaGrille::class)->estMuette())->toBeTrue();
});

it('bloque sur un tarif de grille inférieur au prix propriétaire d’un logement', function (): void {
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    $logement->forceFill(['prix_proprietaire' => 25000, 'prix_vente' => 32000])->save();
    tarifer($g, ['type_logement_id' => $logement->type_logement_id], [['basse', 'longue', 20000]]);

    $anomalie = collect(app(VerificationDeLaGrille::class)->anomalies())->firstWhere('code', 'vente_a_perte');

    expect($anomalie['niveau'])->toBe('bloquant')
        ->and($anomalie['message'])->toContain($logement->refresh()->reference)->toContain('20 000')->toContain('25 000');
});

// ---------------------------------------------------------------- écrans du back office

it('réserve la tarification aux administrateurs', function (Profil $profil, int $statut): void {
    administrateur($profil);

    test()->getJson('/api/v1/backoffice/tarification/verification')->assertStatus($statut);
    test()->getJson('/api/v1/backoffice/referentiels/saisons')->assertStatus($statut);
})->with([[Profil::SuperAdministrateur, 200], [Profil::Administrateur, 200], [Profil::Gestionnaire, 403], [Profil::Client, 403]]);

it('gère saisons, tranches et suppléments, avec leurs règles croisées', function (): void {
    administrateur();

    test()->postJson('/api/v1/backoffice/referentiels/saisons', ['nom' => 'À l’envers', 'categorie' => 'haute', 'date_debut' => '2026-12-31', 'date_fin' => '2026-12-01'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['date_fin']]);
    $id = test()->postJson('/api/v1/backoffice/referentiels/saisons', ['nom' => 'Haute', 'categorie' => 'haute', 'date_debut' => '2026-12-15', 'date_fin' => '2027-01-15'])
        ->assertCreated()->assertJsonPath('data.date_debut', '2026-12-15')->json('data.id');
    // Envoyée seule, la date de fin est quand même comparée à la date de début déjà enregistrée.
    test()->putJson("/api/v1/backoffice/referentiels/saisons/{$id}", ['date_fin' => '2026-12-01'])->assertStatus(422);
    test()->putJson("/api/v1/backoffice/referentiels/saisons/{$id}", ['date_fin' => '2027-02-01'])->assertOk()->assertJsonPath('data.date_fin', '2027-02-01');

    test()->postJson('/api/v1/backoffice/referentiels/tranches-duree', ['nom' => 'Bancale', 'nuits_min' => 10, 'nuits_max' => 5])->assertStatus(422);
    test()->postJson('/api/v1/backoffice/referentiels/supplements', ['code' => 'week_end', 'nom' => 'Week-end', 'mode' => 'par_nuit', 'montant' => 5000])->assertCreated();
    test()->postJson('/api/v1/backoffice/referentiels/supplements', ['code' => 'taxe_fantaisie', 'nom' => 'X', 'mode' => 'par_nuit', 'montant' => 1])->assertStatus(422);
});

it('enregistre des cases de la grille, les trace, et efface une case avec un tarif nul', function (): void {
    $admin = administrateur();
    $g = grilleSaine();
    $type = Logement::factory()->create()->type_logement_id;
    $case = ['saison_id' => $g['basse']->id, 'tranche_duree_id' => $g['courte']->id];

    test()->putJson('/api/v1/backoffice/tarification/grille', ['type_logement_id' => $type, 'lignes' => [[...$case, 'tarif' => 28000]]])
        ->assertOk()->assertJsonStructure(['data' => ['anomalies']]);

    $grille = test()->getJson("/api/v1/backoffice/tarification/grille?type_logement_id={$type}")->assertOk()->json('data');
    $basse = collect($grille['saisons'])->firstWhere('nom', 'Basse saison');
    expect($basse['tarifs'][$g['courte']->id])->toBe(28000)
        ->and($basse['tarifs'][$g['longue']->id])->toBeNull()
        ->and(EntreeAudit::where('sujet_type', 'LigneDeGrille')->where('user_id', $admin->id)->count())->toBe(1);

    test()->putJson('/api/v1/backoffice/tarification/grille', ['type_logement_id' => $type, 'lignes' => [[...$case, 'tarif' => null]]])->assertOk();
    expect(LigneDeGrille::count())->toBe(0)
        ->and(EntreeAudit::where('sujet_type', 'LigneDeGrille')->where('action', 'suppression')->count())->toBe(1);
});

it('refuse une grille qui vise à la fois un type et un logement, ou aucun des deux', function (): void {
    administrateur();
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    $lignes = [['saison_id' => $g['basse']->id, 'tranche_duree_id' => $g['courte']->id, 'tarif' => 1000]];

    test()->putJson('/api/v1/backoffice/tarification/grille', ['lignes' => $lignes])->assertStatus(422);
    test()->putJson('/api/v1/backoffice/tarification/grille', ['type_logement_id' => $logement->type_logement_id, 'logement_id' => $logement->id, 'lignes' => $lignes])->assertStatus(422);
});

it('simule une réservation réelle, nuit par nuit, avec la marge', function (): void {
    administrateur();
    $g = grilleSaine();
    $logement = Logement::factory()->create();
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 30000])->save();
    tarifer($g, ['type_logement_id' => $logement->type_logement_id], [['basse', 'courte', 28000]]);

    $simulation = test()->getJson("/api/v1/backoffice/tarification/simulation?logement_id={$logement->id}&arrivee=2026-10-05&depart=2026-10-08")->assertOk()->json('data');

    expect($simulation['nombre_de_nuits'])->toBe(3)
        ->and($simulation['tranche'])->toBe('1 à 6 nuits')
        ->and($simulation['hebergement_hors_taxes'])->toBe(84000)
        ->and($simulation['marge_hebergement'])->toBe(24000);

    test()->getJson("/api/v1/backoffice/tarification/simulation?logement_id={$logement->id}&arrivee=2026-10-08&depart=2026-10-05")->assertStatus(422);
});
