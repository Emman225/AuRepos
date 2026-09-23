<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Un logement prêt à être réservé (comme ProlongationTest) : publié, résidence active et disponible. */
function unLogementReservable(int $prixVente = 30000): Logement
{
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => $prixVente, 'prix_proprietaire' => 22000])->save();

    return $logement->refresh();
}

beforeEach(function (): void {
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
});

it('calcule taux d’occupation, RevPAR et prix moyen sur un jeu de données connu, y compris un séjour à cheval sur la période', function (): void {
    $client = User::factory()->create();

    // A : entièrement dans la période [01-10] → 3 nuits à 30 000 F = 90 000 F hébergement HT net.
    $logementA = unLogementReservable(30000);
    app(ReservationDeSejour::class)->reserver($client, $logementA, [
        'arrivee' => '2026-11-01', 'depart' => '2026-11-04', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    // B : aucun séjour.
    $logementB = unLogementReservable(30000);

    // C : à cheval sur la période (29/10 → 03/11, 5 nuits à 30 000 F = 150 000 F) : seules les
    // nuits du 01/11 et du 02/11 tombent dans [du, au] → 2/5 du chiffre d'affaires proratés.
    $logementC = unLogementReservable(30000);
    app(ReservationDeSejour::class)->reserver($client, $logementC, [
        'arrivee' => '2026-10-29', 'depart' => '2026-11-03', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-11-01&au=2026-11-10')->assertOk();

    $indicateurs = $reponse->json('data.indicateurs');
    expect($indicateurs)
        ->periode->toBe(['du' => '2026-11-01', 'au' => '2026-11-10', 'jours' => 10])
        ->nombre_de_logements->toBe(3)
        ->nuits_disponibles->toBe(30) // 3 logements × 10 jours
        ->nuits_occupees->toBe(5) // 3 (A) + 2 (C, prorata temporis)
        ->taux_occupation->toBe(16.67) // 5 / 30 × 100, arrondi
        ->ca_hebergement->toBe(150000) // 90 000 (A) + 60 000 (C = 150 000 × 2/5)
        ->revpar->toBe(5000) // 150 000 / 30
        ->prix_moyen->toBe(30000); // 150 000 / 5
});

it('n’inclut pas un séjour annulé dans les indicateurs (il a libéré ses dates)', function (): void {
    $client = User::factory()->create();
    $logement = unLogementReservable(30000);
    $sejour = app(ReservationDeSejour::class)->reserver($client, $logement, [
        'arrivee' => '2026-11-01', 'depart' => '2026-11-04', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
    $sejour->forceFill(['etat' => EtatDuSejour::Annule])->save();

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-11-01&au=2026-11-10')->assertOk();

    $indicateurs = $reponse->json('data.indicateurs');
    expect($indicateurs['nuits_occupees'])->toBe(0);
    expect($indicateurs['ca_hebergement'])->toBe(0);
    // 0.0 se sérialise en JSON comme l'entier 0 (PHP ne garde pas le zéro décimal par défaut).
    expect($indicateurs['taux_occupation'])->toBe(0);
});

it('reste défensif quand un séjour n’a pas de devis figé (donnée hors du parcours normal) : compte dans l’occupation, pas dans le CA', function (): void {
    $logement = unLogementReservable(30000);
    Sejour::factory()->create([
        'logement_id' => $logement->id,
        'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-11-01',
        'depart' => '2026-11-04',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-11-01&au=2026-11-10')->assertOk();

    $indicateurs = $reponse->json('data.indicateurs');
    expect($indicateurs['nuits_occupees'])->toBe(3);
    expect($indicateurs['ca_hebergement'])->toBe(0);
    expect($indicateurs['revpar'])->toBe(0);
});

it('renvoie des indicateurs à zéro (pas d’erreur, pas de division par zéro) sans aucun logement dans le périmètre', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-11-01&au=2026-11-10')->assertOk();

    expect($reponse->json('data.indicateurs'))->toBe([
        'periode' => ['du' => '2026-11-01', 'au' => '2026-11-10', 'jours' => 10],
        'nombre_de_logements' => 0,
        'nuits_disponibles' => 0,
        'nuits_occupees' => 0,
        // 0.0 se sérialise en JSON comme l'entier 0 (PHP ne garde pas le zéro décimal par défaut).
        'taux_occupation' => 0,
        'ca_hebergement' => 0,
        'revpar' => 0,
        'prix_moyen' => 0,
    ]);
});
