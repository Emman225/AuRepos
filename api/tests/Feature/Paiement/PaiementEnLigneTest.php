<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\PaiementEnLigne\Passerelles\PasserelleDEssai;
use App\Domain\PaiementEnLigne\Services\PaiementsEnLigne;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Mail\RecuDeReglementMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
    Mail::fake();
    Storage::fake('local');
    Agence::factory()->create();
    $this->admin = User::factory()->profil(Profil::SuperAdministrateur)->create();
    app(Parametres::class)->enregistrer('comptant', ['paiement_en_ligne_actif' => true], $this->admin);

    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $this->client = User::factory()->create(['email' => 'awa@exemple.ci']);
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
    $this->service = app(PaiementsEnLigne::class);
});

function moi(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Rappel signé, tel que la passerelle l'enverrait. */
function rappel(array $donnees, ?string $signature = null)
{
    return test()->postJson('/api/v1/paiements/rappel', $donnees, ['X-Signature' => $signature ?? PasserelleDEssai::signer($donnees)]);
}

// ---------------------------------------------------------------- ouverture

it('ouvre un paiement au montant calculé par le SERVEUR, jamais celui envoyé par le client', function (): void {
    moi($this->client);

    $reponse = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/paiement", ['montant' => 1])->assertCreated();

    // Le client a demandé 1 F : on ne retient que ce qu'il demande DANS la limite du dû, jamais plus.
    expect($reponse->json('data.montant'))->toBe(1)
        ->and($reponse->json('data.url_paiement'))->toContain('/paiement/retour/');

    // Sans montant : le reste dû entier.
    expect(test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/paiement")->json('data.montant'))->toBe(109386);
});

it('ne laisse jamais une clé de la passerelle sortir vers le client', function (): void {
    config(['paiement.paysecure.cle_api' => 'CLE-SECRETE-PAYSECURE-999', 'paiement.paysecure.secret_rappel' => 'SECRET-RAPPEL-888']);
    moi($this->client);

    $contenu = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/paiement")->assertCreated()->getContent();

    expect($contenu)->not->toContain('CLE-SECRETE')->not->toContain('SECRET-RAPPEL');
});

it('refuse d’ouvrir un paiement dans les cas prévus par le cahier des charges', function (): void {
    moi($this->client);
    $adresse = "/api/v1/client/sejours/{$this->sejour->reference}/paiement";

    app(Parametres::class)->enregistrer('general', ['plafond_paiement_en_ligne' => 50000], $this->admin);
    test()->postJson($adresse)->assertStatus(422)->assertJsonPath('errors.code.0', 'plafond_paiement_en_ligne');

    app(Parametres::class)->enregistrer('general', ['plafond_paiement_en_ligne' => 2000000], $this->admin);
    app(Parametres::class)->enregistrer('comptant', ['paiement_en_ligne_actif' => false], $this->admin);
    test()->postJson($adresse)->assertStatus(422)->assertJsonPath('errors.code.0', 'paiement_en_ligne_indisponible');
});

it('ne laisse pas un client ouvrir un paiement sur le séjour d’un autre', function (): void {
    moi(User::factory()->create());

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/paiement")->assertNotFound();
});

// ---------------------------------------------------------------- confirmation

it('confirme le paiement, crée un règlement effectué, son reçu RL et ses points', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi', mode: 'mobile_money');

    rappel(['reference' => $paiement->reference, 'status' => 'success'])->assertOk()->assertJsonPath('data.etat', 'reussi');

    $paiement->refresh();
    $reglement = Reglement::findOrFail($paiement->reglement_id);
    expect($paiement->etat)->toBe('reussi')
        ->and($reglement->etat->value)->toBe('effectue')
        ->and($reglement->guichet->value)->toBe('en_ligne')
        ->and($reglement->numero_recu)->toBe('RL-2026-001')
        ->and($reglement->mode->value)->toBe('mobile_money')
        ->and(app(SoldeDesSejours::class)->de($this->sejour))->toMatchArray(['encaisse' => 109386, 'reste_du' => 0, 'solde' => true])
        // Le paiement en ligne donne des points comme un règlement au guichet.
        ->and(app(PointsDeFidelite::class)->solde($this->client))->toBe(109);
    Mail::assertQueued(RecuDeReglementMail::class, fn ($m) => $m->hasTo('awa@exemple.ci'));
});

it('ne crée qu’UN règlement, même si la passerelle rappelle dix fois', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi');

    foreach (range(1, 10) as $ignore) {
        rappel(['reference' => $paiement->reference, 'status' => 'success'])->assertOk();
    }
    // Et la vérification au retour du client ne fait pas de onzième.
    moi($this->client);
    test()->getJson("/api/v1/client/paiements/{$paiement->reference}")->assertOk();

    expect(Reglement::where('guichet', 'en_ligne')->count())->toBe(1)
        ->and(app(SoldeDesSejours::class)->de($this->sejour)['encaisse'])->toBe(109386);
});

it('refuse un rappel non signé, mal signé, ou portant une référence inconnue', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi');
    $donnees = ['reference' => $paiement->reference, 'status' => 'success'];

    test()->postJson('/api/v1/paiements/rappel', $donnees)->assertForbidden()->assertJsonPath('errors.code.0', 'rappel_non_authentifie');
    rappel($donnees, 'signature-forgee')->assertForbidden();
    rappel(['reference' => 'PAY-INCONNU', 'status' => 'success'])->assertNotFound();

    expect($paiement->refresh()->etat)->toBe('en_attente')->and(Reglement::count())->toBe(0);
});

it('ne croit pas un rappel qui annonce une réussite : c’est la passerelle, réinterrogée, qui décide', function (): void {
    $paiement = $this->service->initier($this->sejour);
    // La passerelle, elle, n'a rien encaissé.
    PasserelleDEssai::decider($paiement->reference, 'en_attente');

    rappel(['reference' => $paiement->reference, 'status' => 'success', 'amount' => 109386])->assertOk()->assertJsonPath('data.etat', 'en_attente');

    expect(Reglement::count())->toBe(0)->and(app(SoldeDesSejours::class)->de($this->sejour)['encaisse'])->toBe(0);
});

it('retient le montant CONSTATÉ chez la passerelle, et signale l’écart', function (): void {
    $paiement = $this->service->initier($this->sejour);
    // Le client a réglé 50 000 F au lieu des 109 386 F attendus.
    PasserelleDEssai::decider($paiement->reference, 'reussi', montant: 50000);

    rappel(['reference' => $paiement->reference, 'status' => 'success'])->assertOk();

    expect(Reglement::sole()->montant)->toBe(50000)
        ->and(app(SoldeDesSejours::class)->de($this->sejour))->toMatchArray(['encaisse' => 50000, 'reste_du' => 59386, 'solde' => false])
        ->and(EntreeAudit::where('action', 'paiement_montant_different')->count())->toBe(1);
});

it('clôt un paiement refusé par la passerelle, sans rien encaisser', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'echoue');

    rappel(['reference' => $paiement->reference, 'status' => 'failed'])->assertOk();

    expect($paiement->refresh()->etat)->toBe('echoue')->and(Reglement::count())->toBe(0);

    moi($this->client);
    test()->getJson("/api/v1/client/paiements/{$paiement->reference}")->assertOk()
        ->assertJsonPath('data.etat', 'echoue')->assertJsonPath('message', 'Le paiement n’a pas abouti. Vous pouvez réessayer ou régler en agence.');
});

// ---------------------------------------------------------------- filets

it('conclut par la vérification du client quand le rappel n’est jamais arrivé', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi');
    moi($this->client);

    test()->getJson("/api/v1/client/paiements/{$paiement->reference}")->assertOk()
        ->assertJsonPath('data.etat', 'reussi')->assertJsonPath('data.numero_recu', 'RL-2026-001');
});

it('reprend les paiements en attente, et expire ceux que la passerelle n’a jamais confirmés', function (): void {
    $conclu = $this->service->initier($this->sejour);
    $autre = app(ReservationDeSejour::class)->reserver($this->client, $this->sejour->logement, [
        'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
    $abandonne = $this->service->initier($autre);
    PasserelleDEssai::decider($conclu->reference, 'reussi');

    test()->travel(31)->minutes();
    test()->artisan('paiements:reprendre')->expectsOutputToContain('2 paiement(s) repris')->assertSuccessful();

    expect($conclu->refresh()->etat)->toBe('reussi')
        ->and($abandonne->refresh()->etat)->toBe('expire')
        ->and($abandonne->motif_echec)->toContain('Aucune confirmation');
});

it('ne conclut rien quand la passerelle est injoignable : le paiement reste en attente', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'inconnu');

    $this->service->verifier($paiement);

    expect($paiement->refresh()->etat)->toBe('en_attente')->and($paiement->verifications)->toBe(1)->and(Reglement::count())->toBe(0);
});

it('laisse aboutir un paiement en cours même si le site passe en construction', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi');
    app(Parametres::class)->enregistrer('general', ['site_en_construction' => true], $this->admin);

    rappel(['reference' => $paiement->reference, 'status' => 'success'])->assertOk()->assertJsonPath('data.etat', 'reussi');
});

// ---------------------------------------------------------------- garde-fous de la base

it('fait garantir par la base qu’un paiement réussi a son règlement', function (): void {
    $paiement = $this->service->initier($this->sejour);

    expect(fn () => DB::transaction(fn () => DB::table('paiements_en_ligne')->where('reference', $paiement->reference)->update(['etat' => 'reussi'])))
        ->toThrow(QueryException::class, 'paiements_reussi_a_son_reglement');
});

it('n’admet pas de règlement « en ligne » fabriqué à la main au nom d’un agent', function (): void {
    expect(fn () => DB::transaction(fn () => DB::table('reglements')->insert([
        'reference' => 'FAUX', 'sens' => 'encaissement', 'guichet' => 'en_ligne', 'agence_id' => 1, 'tiers_id' => $this->client->id,
        'montant' => 1000, 'mode' => 'carte', 'notes' => 'Fabriqué', 'etat' => 'effectue',
        'saisi_par' => $this->admin->id, 'saisi_le' => now(), 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(QueryException::class, 'reglements_en_ligne_sans_agence');
});

it('refuse d’ouvrir un paiement sur un séjour annulé ou déjà soldé', function (): void {
    $paiement = $this->service->initier($this->sejour);
    PasserelleDEssai::decider($paiement->reference, 'reussi');
    rappel(['reference' => $paiement->reference, 'status' => 'success'])->assertOk();

    expect(fn () => $this->service->initier($this->sejour->refresh()))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('rien_a_payer'));

    $this->sejour->update(['etat' => EtatDuSejour::Annule]);
    expect(fn () => $this->service->initier($this->sejour->refresh()))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('affaire_morte'));
});
