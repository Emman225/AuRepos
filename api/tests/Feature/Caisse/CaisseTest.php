<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->agence = Agence::factory()->create(['nom' => 'Agence Cocody']);
    $personnel = fn (Profil $p, bool $avecAgence = true) => User::factory()->profil($p)->create(['agence_id' => $avecAgence ? $this->agence->id : null]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    [$this->admin1, $this->admin2, $this->admin3] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000, 'acompte_exige' => 30000]);
});

function en(User $utilisateur): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($utilisateur));
}

function encaisser(array $surcharge = [])
{
    return test()->postJson('/api/v1/backoffice/caisse/encaissements', [
        'client_id' => test()->client->id, 'sejours' => [test()->sejour->id], 'montant' => 30000,
        'mode' => 'especes', 'notes' => 'Acompte versé au guichet', ...$surcharge,
    ]);
}

function etape(string $etape, int $id, array $corps = [])
{
    $base = "/api/v1/backoffice/caisse/reglements/{$id}";

    return match ($etape) {
        'valider' => test()->putJson("{$base}/validation"),
        'preuve' => test()->post("{$base}/preuve", ['justificatif' => UploadedFile::fake()->createWithContent('recu.pdf', '%PDF RECU-ORANGE-MONEY-778899')], ['Accept' => 'application/json']),
        'finaliser' => test()->putJson("{$base}/finalisation"),
        default => test()->putJson("{$base}/rejet", $corps),
    };
}

/** Déroule le circuit complet avec les trois administrateurs. */
function allerAuBout(int $id): void
{
    en(test()->admin1);
    etape('valider', $id)->assertOk();
    en(test()->admin2);
    etape('preuve', $id)->assertOk();
    etape('finaliser', $id)->assertOk();
}

// ---------------------------------------------------------------- le circuit

it('mène un encaissement au bout du circuit, en quatre étapes et trois personnes', function (): void {
    Event::fake([ReglementEffectue::class]);
    en($this->caissier);
    $id = encaisser()->assertCreated()->assertJsonPath('data.etat', 'en_attente')->assertJsonPath('data.agence', 'Agence Cocody')->json('data.id');

    en($this->admin1);
    etape('valider', $id)->assertOk()->assertJsonPath('data.etat', 'a_payer');
    en($this->admin2);
    etape('preuve', $id)->assertOk()->assertJsonPath('data.etat', 'preuve_jointe')->assertJsonPath('data.circuit.preuve.fichier', 'recu.pdf');
    etape('finaliser', $id)->assertOk()->assertJsonPath('data.etat', 'effectue');

    $reglement = Reglement::findOrFail($id);
    expect([$reglement->saisi_par, $reglement->valide_par, $reglement->preuve_par, $reglement->finalise_par])
        ->toBe([$this->caissier->id, $this->admin1->id, $this->admin2->id, $this->admin2->id])
        ->and(EntreeAudit::where('sujet_type', 'Reglement')->orderBy('id')->pluck('action')->all())
        ->toBe(['reglement_saisi', 'reglement_valide', 'reglement_preuve_jointe', 'reglement_effectue']);
    Event::assertDispatched(ReglementEffectue::class, fn ($e) => $e->reglement->id === $id);
});

it('ne compte la somme comme payée qu’une fois le circuit terminé', function (): void {
    $soldes = app(SoldeDesSejours::class);
    en($this->caissier);
    $id = encaisser()->json('data.id');

    expect($soldes->de($this->sejour))->toMatchArray(['encaisse' => 0, 'en_cours' => 30000, 'reste_du' => 70000, 'acompte_atteint' => false]);

    allerAuBout($id);

    expect($soldes->de($this->sejour))->toMatchArray(['encaisse' => 30000, 'en_cours' => 0, 'reste_du' => 70000, 'acompte_atteint' => true, 'solde' => false]);
});

// ---------------------------------------------------------------- « le serveur refuse tout écart »

it('refuse que la même personne occupe deux places du circuit', function (): void {
    en($this->admin1);
    $id = encaisser()->json('data.id');

    etape('valider', $id)->assertForbidden()->assertJsonPath('errors.code.0', 'meme_personne_dans_le_circuit');          // l'auteur valide

    en($this->admin2);
    etape('valider', $id)->assertOk();
    etape('preuve', $id)->assertForbidden()->assertJsonPath('errors.code.0', 'meme_personne_dans_le_circuit');           // le valideur prouve
    en($this->admin1);
    etape('preuve', $id)->assertForbidden();                                                                              // l'auteur prouve

    en($this->admin3);
    etape('preuve', $id)->assertOk();
    en($this->admin2);
    etape('finaliser', $id)->assertForbidden()->assertJsonPath('errors.code.0', 'meme_personne_dans_le_circuit');        // un autre finalise
    en($this->admin3);
    etape('finaliser', $id)->assertOk();
});

it('refuse de sauter une étape', function (): void {
    en($this->caissier);
    $id = encaisser()->json('data.id');

    en($this->admin2);
    etape('preuve', $id)->assertStatus(409)->assertJsonPath('errors.code.0', 'etape_hors_sequence');
    etape('finaliser', $id)->assertStatus(409);

    en($this->admin1);
    etape('valider', $id)->assertOk();
    etape('valider', $id)->assertStatus(409);      // déjà validé
});

it('fait tenir les règles de personnes par la BASE, même en contournant l’application', function (): void {
    en($this->admin1);
    $id = encaisser()->json('data.id');
    $forcer = fn (array $valeurs) => fn () => DB::transaction(fn () => DB::table('reglements')->where('id', $id)->update($valeurs));

    expect($forcer(['valide_par' => $this->admin1->id]))->toThrow(QueryException::class, 'reglements_validation_par_un_autre')
        ->and($forcer(['etat' => 'effectue']))->toThrow(QueryException::class, 'reglements_etapes_dans_l_ordre')
        ->and($forcer(['valide_par' => $this->admin2->id, 'preuve_par' => $this->admin2->id]))->toThrow(QueryException::class, 'reglements_preuve_par_un_troisieme')
        ->and($forcer(['valide_par' => $this->admin2->id, 'preuve_par' => $this->admin3->id, 'finalise_par' => $this->admin1->id]))
        ->toThrow(QueryException::class, 'reglements_finalisation_par_le_meme');
});

it('réserve validation, preuve, finalisation et rejet aux administrateurs', function (): void {
    en($this->caissier);
    $id = encaisser()->json('data.id');

    etape('valider', $id)->assertForbidden();
    etape('rejet', $id, ['motif' => 'Tentative'])->assertForbidden();
    test()->postJson('/api/v1/backoffice/caisse/decaissements', [])->assertForbidden();
});

// ---------------------------------------------------------------- guichet

it('n’encaisse jamais pour une agence choisie : c’est celle du caissier, et il en faut une', function (): void {
    $autre = Agence::factory()->create();
    en($this->caissier);
    encaisser(['agence_id' => $autre->id])->assertCreated()->assertJsonPath('data.agence', 'Agence Cocody');

    en(User::factory()->profil(Profil::Gestionnaire)->create());    // sans agence
    encaisser()->assertForbidden()->assertJsonPath('errors.code.0', 'caissier_sans_agence');
});

it('exige les notes et refuse un mode de règlement désactivé', function (): void {
    en($this->caissier);
    encaisser(['notes' => ''])->assertStatus(422)->assertJsonStructure(['errors' => ['notes']]);

    app(Parametres::class)->enregistrer('comptant', ['cheque' => false], User::factory()->profil(Profil::SuperAdministrateur)->create());
    encaisser(['mode' => 'cheque'])->assertStatus(422)->assertJsonPath('errors.code.0', 'mode_de_reglement_inactif');
});

it('réserve la place d’une tranche saisie : on n’encaisse pas deux fois le même reste dû', function (): void {
    en($this->caissier);
    encaisser(['montant' => 70000])->assertCreated();

    encaisser(['montant' => 40000])->assertStatus(422)->assertJsonPath('errors.code.0', 'montant_superieur_au_reste_du');
    encaisser(['montant' => 30000])->assertCreated();
    encaisser(['montant' => 1])->assertStatus(422);
});

it('rend la place réservée quand le règlement est rejeté', function (): void {
    en($this->caissier);
    $id = encaisser(['montant' => 100000])->json('data.id');

    en($this->admin1);
    etape('rejet', $id)->assertStatus(422);     // motif obligatoire
    etape('rejet', $id, ['motif' => 'Billet refusé par la banque'])->assertOk()->assertJsonPath('data.etat', 'rejete');

    expect(app(SoldeDesSejours::class)->de($this->sejour)['reste_du'])->toBe(100000);
    etape('valider', $id)->assertStatus(409);   // un règlement rejeté ne repart pas
});

it('ne rejette plus un règlement effectué', function (): void {
    en($this->caissier);
    $id = encaisser()->json('data.id');
    allerAuBout($id);

    etape('rejet', $id, ['motif' => 'Trop tard'])->assertStatus(409)->assertJsonPath('errors.code.0', 'reglement_deja_traite');
});

it('impute plusieurs affaires du même client, de la plus ancienne à la plus récente', function (): void {
    $this->travel(1)->days();
    $recent = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 50000, 'arrivee' => '2026-12-01', 'depart' => '2026-12-03']);
    en($this->caissier);

    $reponse = encaisser(['sejours' => [$recent->id, $this->sejour->id], 'montant' => 120000])->assertCreated();

    expect($reponse->json('data.imputations'))->toBe([
        ['affaire' => $this->sejour->refresh()->reference, 'montant' => 100000],   // la plus ancienne d'abord, soldée
        ['affaire' => $recent->refresh()->reference, 'montant' => 20000],          // le reste sur la suivante
    ]);
});

it('ne mélange jamais deux clients, et n’encaisse pas sur une affaire morte', function (): void {
    $autre = Sejour::factory()->create(['client_id' => User::factory()->create()->id, 'net_a_payer' => 50000, 'arrivee' => '2026-12-01', 'depart' => '2026-12-03']);
    en($this->caissier);

    encaisser(['sejours' => [$this->sejour->id, $autre->id]])->assertStatus(422)->assertJsonPath('errors.code.0', 'clients_melanges');

    $this->sejour->update(['etat' => EtatDuSejour::Annule]);
    encaisser()->assertStatus(422)->assertJsonPath('errors.code.0', 'affaire_morte');
});

it('liste au guichet les affaires d’un client avec leur reste dû', function (): void {
    en($this->caissier);
    encaisser(['montant' => 30000]);

    $affaires = test()->getJson("/api/v1/backoffice/caisse/clients/{$this->client->id}/affaires")->assertOk()->json('data.affaires');

    expect($affaires)->toHaveCount(1)->and($affaires[0])->toMatchArray(['net_a_payer' => 100000, 'en_cours' => 30000, 'reste_du' => 70000, 'acompte_exige' => 30000]);
});

// ---------------------------------------------------------------- preuve et décaissements

it('chiffre la preuve sur le disque, la rend en clair par la seule route autorisée, et trace la consultation', function (): void {
    en($this->caissier);
    $id = encaisser()->json('data.id');
    en($this->admin1);
    etape('valider', $id);
    en($this->admin2);
    etape('preuve', $id);

    $reglement = Reglement::findOrFail($id);
    expect(Storage::disk('local')->get($reglement->preuve_chemin))->not->toContain('RECU-ORANGE-MONEY');

    $reponse = test()->get("/api/v1/backoffice/caisse/reglements/{$id}/preuve")->assertOk();
    expect($reponse->getContent())->toContain('RECU-ORANGE-MONEY-778899')
        ->and(EntreeAudit::where('action', 'consultation_preuve')->count())->toBe(1);

    en($this->caissier);
    test()->get("/api/v1/backoffice/caisse/reglements/{$id}/preuve", ['Accept' => 'application/json'])->assertForbidden();
});

it('refuse un justificatif qui n’est ni un PDF ni une image', function (): void {
    en($this->caissier);
    $id = encaisser()->json('data.id');
    en($this->admin1);
    etape('valider', $id);
    en($this->admin2);

    test()->post("/api/v1/backoffice/caisse/reglements/{$id}/preuve", ['justificatif' => UploadedFile::fake()->create('macro.docm', 20)], ['Accept' => 'application/json'])->assertStatus(422);
});

it('soumet un décaissement au même circuit de preuve', function (): void {
    $proprietaire = User::factory()->profil(Profil::Proprietaire)->create();
    en($this->admin1);
    $id = test()->postJson('/api/v1/backoffice/caisse/decaissements', [
        'beneficiaire_id' => $proprietaire->id, 'montant' => 250000, 'mode' => 'virement', 'notes' => 'Reversement de septembre',
    ])->assertCreated()->assertJsonPath('data.sens', 'decaissement')->assertJsonPath('data.guichet', 'dettes_partenaires')->json('data.id');

    etape('valider', $id)->assertForbidden();      // l'auteur ne valide pas
    en($this->admin2);
    etape('valider', $id)->assertOk();
    en($this->admin3);
    etape('preuve', $id)->assertOk();
    etape('finaliser', $id)->assertOk()->assertJsonPath('data.etat', 'effectue');

    // Un décaissement n'entre jamais dans ce que le client a payé.
    expect(app(SoldeDesSejours::class)->de($this->sejour)['encaisse'])->toBe(0);
});

it('filtre la liste des règlements et n’offre à chacun que les actions qui lui reviennent', function (): void {
    en($this->admin1);
    $id = encaisser()->json('data.id');

    $pourLAuteur = test()->getJson('/api/v1/backoffice/caisse/reglements?etat=en_attente&sens=encaissement')->assertOk()->json('data.elements.0');
    expect($pourLAuteur['id'])->toBe($id)->and($pourLAuteur['actions'])->toMatchArray(['valider' => false, 'rejeter' => true]);

    en($this->admin2);
    expect(test()->getJson('/api/v1/backoffice/caisse/reglements')->json('data.elements.0.actions.valider'))->toBeTrue()
        ->and(test()->getJson('/api/v1/backoffice/caisse/reglements?etat=effectue')->json('data.pagination.total'))->toBe(0);
});

it('ferme la caisse aux profils hors exploitation', function (Profil $profil): void {
    en(User::factory()->profil($profil)->create());

    test()->getJson('/api/v1/backoffice/caisse/reglements')->assertForbidden();
    encaisser()->assertForbidden();
})->with([Profil::Client, Profil::Proprietaire, Profil::Gouvernante]);

it('garde l’état connu de la base : aucun état inventé', function (): void {
    expect(array_column(EtatDuReglement::cases(), 'value'))->toBe(['en_attente', 'a_payer', 'preuve_jointe', 'effectue', 'rejete']);
});

it('n’enregistre chaque écouteur de fin de circuit qu’UNE fois', function (): void {
    // Défaut rencontré : Laravel découvre seul les écouteurs d'app/Listeners ; les déclarer en plus
    // les doublait. Le reçu ne partait qu'une fois grâce à son garde-fou, mais les points de fidélité
    // étaient attribués deux fois. Ce test empêche le doublon de revenir.
    $ecouteurs = Event::getRawListeners()[ReglementEffectue::class] ?? [];
    $noms = array_map(fn ($e) => is_string($e) ? explode('@', $e)[0] : 'closure', $ecouteurs);

    expect($noms)->toEqual(array_unique($noms));
});
