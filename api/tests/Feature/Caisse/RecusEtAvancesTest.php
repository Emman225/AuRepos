<?php

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\AvanceClient;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Avances;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\Numerotation;
use App\Domain\Caisse\Services\Recus;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Mail\RecuDeReglementMail;
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
    $agence = Agence::factory()->create(['nom' => 'Agence Cocody']);
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    [$this->a1, $this->a2] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->client = User::factory()->create(['email' => 'awa@exemple.ci', 'nom' => 'Koné', 'prenoms' => 'Awa']);
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000, 'acompte_exige' => 30000]);
});

function connecte(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Mène un règlement au bout du circuit avec les deux administrateurs. */
function finaliser(Reglement $reglement): Reglement
{
    $caisse = app(Caisse::class);
    $caisse->valider($reglement, test()->a1);
    $caisse->joindreLaPreuve($reglement->refresh(), test()->a2, UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'));
    $caisse->finaliser($reglement->refresh(), test()->a2);

    return $reglement->refresh();
}

function encaisserSur(Sejour $sejour, int $montant, bool $surplusEnAvance = false): Reglement
{
    return app(Caisse::class)->saisirUnEncaissement(
        test()->caissier, test()->client, [$sejour->id], $montant, ModeDeReglement::Especes, 'Versement au guichet', surplusEnAvance: $surplusEnAvance,
    );
}

function deposerUneAvance(int $montant): Reglement
{
    return finaliser(app(Caisse::class)->saisirUnDepotDAvance(test()->caissier, test()->client, $montant, ModeDeReglement::Especes, 'Dépôt sans réservation'));
}

// ---------------------------------------------------------------- reçus

it('n’attribue le numéro de reçu qu’à la finalisation, et envoie le reçu une seule fois', function (): void {
    $reglement = encaisserSur($this->sejour, 30000);
    expect($reglement->numero_recu)->toBeNull();

    app(Caisse::class)->valider($reglement, $this->a1);
    app(Caisse::class)->joindreLaPreuve($reglement->refresh(), $this->a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    expect($reglement->refresh()->numero_recu)->toBeNull();      // preuve jointe : toujours pas de reçu

    app(Caisse::class)->finaliser($reglement->refresh(), $this->a2);

    $reglement = $reglement->refresh();
    expect($reglement->numero_recu)->toBe('RC-2026-001')->and($reglement->recu_envoye_le)->not->toBeNull();
    Mail::assertQueued(RecuDeReglementMail::class, fn ($m) => $m->hasTo('awa@exemple.ci'));

    // L'écouteur ne renvoie pas : l'envoi automatique est unique.
    app(Recus::class)->envoyerUneFois($reglement);
    Mail::assertQueuedCount(1);
});

it('numérote SANS TROU, avec un compteur commun à toute la caisse', function (): void {
    finaliser(encaisserSur($this->sejour, 30000));                       // RC-2026-001
    $depot = deposerUneAvance(50000);                                     // RA-2026-002
    $autre = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 40000, 'arrivee' => '2026-12-01', 'depart' => '2026-12-03']);
    finaliser(app(Caisse::class)->saisirUnEncaissement(
        $this->caissier, $this->client, [$autre->id], 10000, ModeDeReglement::Especes, 'Acompte', Guichet::CreancesATerme,
    ));                                                                   // RC-CT-2026-003

    expect(Reglement::orderBy('id')->pluck('numero_recu')->all())->toBe(['RC-2026-001', 'RA-2026-002', 'RC-CT-2026-003'])
        ->and($depot->numero_recu)->toBe('RA-2026-002');
});

it('rend son numéro à une transaction annulée : la suite reste continue', function (): void {
    $numerotation = app(Numerotation::class);
    expect($numerotation->suivant('essai', 2026))->toBe(1);

    try {
        DB::transaction(function () use ($numerotation): void {
            $numerotation->suivant('essai', 2026);
            throw new RuntimeException('opération annulée');
        });
    } catch (RuntimeException) {
    }

    // Une séquence PostgreSQL aurait sauté à 3 ; le compteur, lui, rend le 2.
    expect($numerotation->suivant('essai', 2026))->toBe(2);
});

it('produit un reçu PDF conservé, avec l’identité de l’entreprise, le montant en lettres et le détail', function (): void {
    app(Parametres::class)->enregistrer('entreprise', ['raison_sociale' => 'DALAKOUN SARL', 'ncc' => '1234567A', 'siege' => 'Cocody, Abidjan'], $this->a1);
    $reglement = finaliser(encaisserSur($this->sejour, 66361));

    $pdf = app(Recus::class)->pdf($reglement);

    expect($pdf)->toStartWith('%PDF')
        ->and(Storage::disk('local')->exists($reglement->refresh()->recu_chemin))->toBeTrue()
        // Orthographe rectifiée (traits d’union partout), et accord de « vingt » et « cent » en fin de nombre.
        ->and(Recus::enLettres(66361))->toBe('soixante-six mille trois cent soixante-et-un')
        ->and(Recus::enLettres(80))->toBe('quatre-vingts')
        ->and(Recus::enLettres(80000))->toBe('quatre-vingt mille')
        ->and(Recus::enLettres(90))->toBe('quatre-vingt-dix')
        ->and(Recus::enLettres(300))->toBe('trois cents')
        ->and(Recus::enLettres(300000))->toBe('trois cent mille');

    // Le fichier conservé est bien celui qu'on ressert.
    Storage::disk('local')->put($reglement->recu_chemin, '%PDF-CONSERVE');
    expect(app(Recus::class)->pdf($reglement->refresh()))->toBe('%PDF-CONSERVE');
});

it('refuse un reçu tant que le règlement n’est pas effectué', function (): void {
    $reglement = encaisserSur($this->sejour, 30000);
    connecte($this->caissier);

    test()->getJson("/api/v1/backoffice/caisse/reglements/{$reglement->id}/recu")->assertNotFound()->assertJsonPath('errors.code.0', 'recu_inexistant');
});

it('laisse le client lire SES reçus et son avance, mais pas ceux d’un autre', function (): void {
    finaliser(encaisserSur($this->sejour, 30000));
    test()->travel(1)->minutes();       // sinon les deux règlements portent la même seconde et leur ordre n’est pas garanti
    deposerUneAvance(20000);
    connecte($this->client);

    $paiements = test()->getJson('/api/v1/client/paiements')->assertOk()->json('data');
    expect($paiements['avance_disponible'])->toBe(20000)
        // Séjour réglé « en agence » (mode par défaut) : net à payer 100000, 30000 déjà encaissés.
        ->and($paiements['montant_a_regler_en_agence'])->toBe(70000)
        ->and(array_column($paiements['reglements'], 'numero_recu'))->toBe(['RA-2026-002', 'RC-2026-001']);

    test()->get('/api/v1/client/recus/RC-2026-001')->assertOk()->assertHeader('Content-Type', 'application/pdf');

    connecte(User::factory()->create());
    test()->getJson('/api/v1/client/recus/RC-2026-001')->assertNotFound();
});

it('renvoie un reçu à la demande, sans changer la date d’envoi automatique', function (): void {
    $reglement = finaliser(encaisserSur($this->sejour, 30000));
    $envoye = $reglement->recu_envoye_le;
    connecte($this->caissier);

    test()->travel(1)->hours();
    test()->postJson("/api/v1/backoffice/caisse/reglements/{$reglement->id}/recu/renvoi")->assertOk()->assertJsonPath('data.envoye', true);

    Mail::assertQueuedCount(2);
    expect($reglement->refresh()->recu_envoye_le?->toIso8601String())->toBe($envoye?->toIso8601String());
});

it('finalise quand même si l’envoi du reçu échoue', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('serveur de messagerie en panne'));
    $reglement = finaliser(encaisserSur($this->sejour, 30000));

    expect($reglement->numero_recu)->toBe('RC-2026-001')->and($reglement->recu_envoye_le)->toBeNull();
});

// ---------------------------------------------------------------- avances

it('constitue l’avance à la finalisation du dépôt, et pas avant', function (): void {
    connecte($this->caissier);
    $id = test()->postJson('/api/v1/backoffice/caisse/avances', [
        'client_id' => $this->client->id, 'montant' => 50000, 'mode' => 'especes', 'notes' => 'Dépôt sans réservation',
    ])->assertCreated()->assertJsonPath('data.guichet', 'avances')->json('data.id');

    expect(app(Avances::class)->disponible($this->client))->toBe(0);

    finaliser(Reglement::findOrFail($id));

    expect(app(Avances::class)->disponible($this->client))->toBe(50000)
        ->and(AvanceClient::sole())->toMatchArray(['montant' => 50000, 'solde' => 50000]);
});

it('garde le surplus en avance quand le caissier le demande, et le refuse sinon', function (): void {
    connecte($this->caissier);
    $trop = ['client_id' => $this->client->id, 'sejours' => [$this->sejour->id], 'montant' => 150000, 'mode' => 'especes', 'notes' => 'Versement'];

    $refus = test()->postJson('/api/v1/backoffice/caisse/encaissements', $trop)->assertStatus(422);
    expect($refus->json('message'))->toContain('enregistrer ce surplus comme avance');

    $id = test()->postJson('/api/v1/backoffice/caisse/encaissements', [...$trop, 'surplus_en_avance' => true])->assertCreated()->json('data.id');
    finaliser(Reglement::findOrFail($id));

    expect(app(SoldeDesSejours::class)->de($this->sejour))->toMatchArray(['encaisse' => 100000, 'reste_du' => 0, 'solde' => true])
        ->and(app(Avances::class)->disponible($this->client))->toBe(50000);
});

it('déduit l’avance d’office d’une nouvelle réservation, du dépôt le plus ancien au plus récent', function (): void {
    deposerUneAvance(30000);                    // le plus ancien
    test()->travel(1)->minutes();
    deposerUneAvance(50000);
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();

    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    // 3 nuits à 30 000 F : 109 386 F. L'avance de 80 000 F s'impute d'office.
    expect(app(SoldeDesSejours::class)->de($sejour))->toMatchArray(['encaisse' => 80000, 'reste_du' => 29386])
        ->and(app(Avances::class)->disponible($this->client))->toBe(0)
        ->and(AvanceClient::orderBy('id')->pluck('solde')->all())->toBe([0, 0]);

    $imputation = Reglement::where('mode', ModeDeReglement::Avance)->sole();
    expect($imputation->numero_recu)->toStartWith('AV-2026-')->and($imputation->etat->value)->toBe('effectue');
});

it('rend l’avance au client quand sa demande est annulée, mais ne la rend pas deux fois', function (): void {
    deposerUneAvance(50000);
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
    expect(app(Avances::class)->disponible($this->client))->toBe(0);

    connecte($this->client);
    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/annulation")->assertOk();

    expect(app(Avances::class)->disponible($this->client))->toBe(50000)
        ->and(Reglement::where('mode', ModeDeReglement::Avance)->sole()->etat->value)->toBe('rejete');

    app(Avances::class)->recrediterPour($sejour->refresh(), 'Nouvel essai');
    expect(app(Avances::class)->disponible($this->client))->toBe(50000);
});

it('fait expirer une demande que l’avance ne suffit pas à confirmer, et rend l’avance', function (): void {
    deposerUneAvance(5000);
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    test()->travel(25)->hours();
    test()->artisan('sejours:expirer-demandes')->expectsOutputToContain('1 demande(s) expirée(s)');

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Annule)
        ->and(app(Avances::class)->disponible($this->client))->toBe(5000);
});

it('ne fait PAS expirer une demande dont l’avance couvre l’acompte', function (): void {
    deposerUneAvance(50000);
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    test()->travel(25)->hours();
    test()->artisan('sejours:expirer-demandes')->expectsOutputToContain('Aucune demande');

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Demande);
});

it('montre au guichet la situation des avances d’un client', function (): void {
    deposerUneAvance(50000);
    connecte($this->caissier);

    $situation = test()->getJson("/api/v1/backoffice/caisse/clients/{$this->client->id}/avances")->assertOk()->json('data');

    expect($situation['disponible'])->toBe(50000)
        ->and($situation['depots'][0])->toMatchArray(['montant' => 50000, 'solde' => 50000, 'utilise' => 0, 'numero_recu' => 'RA-2026-001']);
});

it('ferme le guichet des avances aux profils hors exploitation', function (Profil $profil): void {
    connecte(User::factory()->profil($profil)->create());

    test()->postJson('/api/v1/backoffice/caisse/avances', [])->assertForbidden();
    test()->getJson("/api/v1/backoffice/caisse/clients/{$this->client->id}/avances")->assertForbidden();
})->with([Profil::Client, Profil::Proprietaire]);

it('met réellement le courriel du reçu en file d’attente : un PDF binaire ne s’y sérialise pas', function (): void {
    // Défaut constaté sur l'API réelle : le PDF passé au constructeur faisait échouer l'encodage JSON du
    // travail (« Malformed UTF-8 characters »). Mail::fake() ne sérialise pas : ce test force l'encodage.
    $reglement = finaliser(encaisserSur($this->sejour, 30000));

    $travail = serialize(new RecuDeReglementMail($reglement));

    expect(json_encode(['data' => ['command' => $travail]]))->not->toBeFalse()
        ->and(json_last_error())->toBe(JSON_ERROR_NONE)
        // Le PDF n'est pas embarqué : il est relu depuis le fichier conservé au moment de l'envoi.
        ->and(strlen($travail))->toBeLessThan(2000);
});
