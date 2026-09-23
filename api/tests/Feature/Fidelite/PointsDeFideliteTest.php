<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Models\MouvementPoints;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CycleDuSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
    Mail::fake();
    Storage::fake('local');
    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    [$this->a1, $this->a2] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->client = User::factory()->create();
    $this->logement = Logement::factory()->create();
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $this->logement->refresh();
    $this->points = app(PointsDeFidelite::class);
});

function jeSuis(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Encaisse un montant sur un séjour, jusqu'au bout du circuit de preuve. */
function encaisserPour(Sejour $sejour, int $montant): Reglement
{
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement(test()->caissier, test()->client, [$sejour->id], $montant, ModeDeReglement::Especes, 'Versement');
    $caisse->valider($r, test()->a1);
    $caisse->joindreLaPreuve($r->refresh(), test()->a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), test()->a2);

    return $r->refresh();
}

function reserverAvec(int $points = 0): Sejour
{
    return app(ReservationDeSejour::class)->reserver(test()->client, test()->logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence', 'points_utilises' => $points,
    ]);
}

/** Crédite le client sans passer par la caisse, pour préparer un cas. */
function crediter(int $points): void
{
    MouvementPoints::create([
        'client_id' => test()->client->id, 'nature' => 'acquisition', 'points' => $points,
        'libelle' => 'Crédit de départ', 'montant_par_point' => 1000, 'valeur_du_point' => 10,
    ]);
}

// ---------------------------------------------------------------- acquisition

it('donne 1 point par tranche de 1 000 F encaissés, résultat TRONQUÉ', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000]);

    encaisserPour($sejour, 2999);

    // 2 999 F → 2 points, pas 3.
    expect($this->points->solde($this->client))->toBe(2)
        ->and($this->points->valeurDuSolde($this->client))->toBe(20);
});

it('n’attribue les points qu’une fois le circuit de preuve terminé', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000]);
    $reglement = app(Caisse::class)->saisirUnEncaissement($this->caissier, $this->client, [$sejour->id], 50000, ModeDeReglement::Especes, 'Acompte');

    app(Caisse::class)->valider($reglement, $this->a1);
    expect($this->points->solde($this->client))->toBe(0);

    app(Caisse::class)->joindreLaPreuve($reglement->refresh(), $this->a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    app(Caisse::class)->finaliser($reglement->refresh(), $this->a2);

    expect($this->points->solde($this->client))->toBe(50);
});

it('ne redonne pas de points sur une imputation d’avance : ils ont été gagnés au dépôt', function (): void {
    $depot = app(Caisse::class)->saisirUnDepotDAvance($this->caissier, $this->client, 50000, ModeDeReglement::Especes, 'Dépôt');
    app(Caisse::class)->valider($depot, $this->a1);
    app(Caisse::class)->joindreLaPreuve($depot->refresh(), $this->a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    app(Caisse::class)->finaliser($depot->refresh(), $this->a2);
    expect($this->points->solde($this->client))->toBe(50);

    reserverAvec();   // l'avance s'impute d'office

    expect($this->points->solde($this->client))->toBe(50);   // et non 100
});

it('suit le barème paramétré, et le fige sur chaque mouvement', function (): void {
    app(Parametres::class)->enregistrer('general', ['fidelite_montant_par_point' => 500, 'fidelite_valeur_du_point' => 25], $this->a1);
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000]);

    encaisserPour($sejour, 10000);
    expect($this->points->solde($this->client))->toBe(20)->and($this->points->valeurDuSolde($this->client))->toBe(500);

    // Le barème change : l'historique ne se réécrit pas.
    app(Parametres::class)->enregistrer('general', ['fidelite_montant_par_point' => 1000, 'fidelite_valeur_du_point' => 10], $this->a1);
    expect(MouvementPoints::latest('id')->first())->toMatchArray(['montant_par_point' => 500, 'valeur_du_point' => 25]);
});

// ---------------------------------------------------------------- utilisation

it('déduit les points du séjour, à 10 F le point', function (): void {
    crediter(500);

    $sejour = reserverAvec(500);

    // 3 nuits à 30 000 F = 90 000 HT ; 500 points = 5 000 F retirés du HT.
    expect($sejour->getAttribute('points_utilises'))->toBe(500)
        ->and($sejour->getAttribute('reduction_points'))->toBe(5000)
        ->and($sejour->devis['hebergement_net_ht'])->toBe(85000)
        ->and($sejour->net_a_payer)->toBe(103309)      // 85 000 + 18 % = 100 300 ; + 3 % = 103 309 (au lieu de 109 386)
        ->and($this->points->solde($this->client))->toBe(0);
});

it('ne laisse jamais les points faire tomber le séjour sous le minimum à payer', function (): void {
    crediter(100000);   // 1 000 000 F de points sur un séjour de 90 000 F HT

    $sejour = reserverAvec(100000);

    expect($sejour->net_a_payer)->toBeGreaterThanOrEqual(1000)->toBeLessThan(1010)
        // Seuls les points réellement utilisés sont consommés : le reste demeure au client.
        ->and($this->points->solde($this->client))->toBeGreaterThan(90000)
        ->and($sejour->getAttribute('points_utilises'))->toBe(100000 - $this->points->solde($this->client));
});

it('ne consomme jamais plus que le solde, même si le client en demande plus', function (): void {
    crediter(100);

    $sejour = reserverAvec(999999);

    expect($sejour->getAttribute('points_utilises'))->toBe(100)
        ->and($this->points->solde($this->client))->toBe(0);
});

it('refuse de consommer des points qu’on n’a pas', function (): void {
    expect(fn () => $this->points->utiliserSur(Sejour::factory()->create(['client_id' => $this->client->id]), 50))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('points_insuffisants'));
});

// ---------------------------------------------------------------- reprise

it('rend au client les points utilisés quand son séjour est annulé', function (): void {
    crediter(500);
    $sejour = reserverAvec(500);
    expect($this->points->solde($this->client))->toBe(0);

    jeSuis($this->client);
    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/annulation")->assertOk();

    expect($this->points->solde($this->client))->toBe(500)
        ->and(MouvementPoints::where('nature', 'restitution')->sole()->points)->toBe(500);
});

it('reprend les points gagnés sur un séjour annulé, sans jamais rendre le solde négatif', function (): void {
    $sejour = reserverAvec();
    encaisserPour($sejour, 50000);
    expect($this->points->solde($this->client))->toBe(50);

    app(CycleDuSejour::class)->annuler($sejour->refresh(), $this->a1, 'Empêchement du client');

    expect($this->points->solde($this->client))->toBe(0)
        ->and(MouvementPoints::where('nature', 'reprise')->sole()->points)->toBe(-50);
});

it('ne reprend que ce qui reste quand le client a déjà dépensé ses points ailleurs', function (): void {
    $sejour = reserverAvec();
    encaisserPour($sejour, 50000);          // +50 points
    $autre = app(ReservationDeSejour::class)->reserver($this->client, $this->logement->refresh(), [
        'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'adultes' => 2, 'mode_reglement' => 'agence', 'points_utilises' => 40,
    ]);   // le client en dépense 40 sur un AUTRE séjour
    expect($this->points->solde($this->client))->toBe(10);

    app(CycleDuSejour::class)->annuler($sejour->refresh(), $this->a1, 'Annulation');

    // On reprend les 10 restants, pas les 50 : le solde ne devient pas négatif.
    expect($this->points->solde($this->client))->toBe(0)
        ->and($autre->refresh()->getAttribute('points_utilises'))->toBe(40);
});

// ---------------------------------------------------------------- cohérence et écrans

it('fait refuser par la base un mouvement au sens incohérent', function (): void {
    expect(fn () => DB::transaction(fn () => DB::table('mouvements_points')->insert([
        'client_id' => $this->client->id, 'nature' => 'acquisition', 'points' => -5, 'libelle' => 'Incohérent',
        'montant_par_point' => 1000, 'valeur_du_point' => 10, 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'points_sens_coherent');
});

it('donne au client son relevé de points, ligne par ligne', function (): void {
    crediter(500);
    reserverAvec(200);
    jeSuis($this->client);

    $fidelite = test()->getJson('/api/v1/client/paiements')->assertOk()->json('data.fidelite');

    expect($fidelite)->toMatchArray(['solde' => 300, 'valeur' => 3000, 'valeur_du_point' => 10, 'montant_par_point' => 1000])
        ->and($fidelite['mouvements'][0])->toMatchArray(['nature' => 'utilisation', 'points' => -200, 'valeur' => 2000]);
});

it('annonce au client connecté ce qu’il peut retirer, et ne dit rien au visiteur', function (): void {
    crediter(500);
    $adresse = "/api/v1/catalogue/logements/{$this->logement->reference}/estimation";
    $saisie = ['arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2];

    test()->postJson($adresse, $saisie)->assertOk()->assertJsonPath('data.fidelite', null);

    jeSuis($this->client);
    $fidelite = test()->postJson($adresse, $saisie)->assertOk()->json('data.fidelite');

    expect($fidelite)->toMatchArray(['solde' => 500, 'utilisables' => 500, 'valeur' => 5000, 'plafonne' => false]);
});

it('montre les points utilisés sur la fiche du séjour du client', function (): void {
    crediter(500);
    $sejour = reserverAvec(500);
    jeSuis($this->client);

    test()->getJson("/api/v1/client/sejours/{$sejour->reference}")->assertOk()
        ->assertJsonPath('data.points_utilises', 500)->assertJsonPath('data.reduction_points', 5000);
});
