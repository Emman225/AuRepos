<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use App\Mail\BonDeMiseADispositionMail;
use App\Mail\SejourConfirmeMail;
use App\Support\Api\ErreurMetier;
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
    $this->gestionnaire = $personnel(Profil::Gestionnaire);
    $this->admins = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];

    $residence = Residence::factory()->create(['adresse' => 'Rue des Jardins, lot 45', 'consignes_acces' => 'Sonner au portail vert']);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create(['email' => 'awa@exemple.ci']);
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
    // Le gestionnaire du circuit de preuve n'agit que sur SES résidences (CdC § 9.5).
    $this->gestionnaire->residences()->attach($residence->id);
});

function comme(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Encaisse un montant en allant AU BOUT du circuit de preuve, avec les trois administrateurs. */
function encaisserReellement(int $montant, bool $jusquAuBout = true): void
{
    $caisse = app(Caisse::class);
    $t = test();
    $r = $caisse->saisirUnEncaissement($t->gestionnaire, $t->client, [$t->sejour->id], $montant, ModeDeReglement::Especes, 'Versement au guichet');
    if (! $jusquAuBout) {
        return;
    }
    $caisse->valider($r, $t->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $t->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $t->admins[1]);
}

function codeRecuParLeClient(): string
{
    $code = null;
    Mail::assertQueued(SejourConfirmeMail::class, function (SejourConfirmeMail $m) use (&$code) {
        $code = $m->code;

        return true;
    });

    return (string) $code;
}

// ---------------------------------------------------------------- « vérifier que le paiement le permet »

it('refuse de confirmer tant que l’acompte n’est pas RÉELLEMENT encaissé', function (): void {
    comme($this->gestionnaire);
    $adresse = "/api/v1/backoffice/sejours/{$this->sejour->id}/confirmation";

    test()->postJson($adresse)->assertStatus(422)->assertJsonPath('errors.code.0', 'confirmation_impossible');

    // Une tranche saisie mais non finalisée ne confirme rien.
    encaisserReellement($this->sejour->acompte_exige, jusquAuBout: false);
    $reponse = test()->postJson($adresse)->assertStatus(422);
    expect($reponse->json('message'))->toContain('encore dans le circuit de preuve');

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Demande);
});

it('confirme une fois l’acompte encaissé : bon, code, courriels, et le séjour n’expire plus', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    comme($this->gestionnaire);
    $agent = User::factory()->profil(Profil::AgentTerrain)->create();

    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/confirmation", ['agent_accueil_id' => $agent->id])
        ->assertOk()->assertJsonPath('data.etat', 'confirme')->assertJsonPath('data.code_d_arrivee_emis', true)->assertJsonPath('data.agent_accueil_id', $agent->id);

    $sejour = $this->sejour->refresh();
    $bon = BonDeMiseADisposition::where('sejour_id', $sejour->id)->sole();
    expect($sejour->expire_le)->toBeNull()
        ->and($sejour->getAttribute('confirme_par'))->toBe($this->gestionnaire->id)
        ->and($bon->numero)->toMatch('/^BMD-\d{6}$/')
        ->and($bon->nuitees)->toBe(3)->and($bon->prix_proprietaire_par_nuit)->toBe(22000)->and($bon->etat)->toBe('en_attente');

    Mail::assertQueued(SejourConfirmeMail::class, fn ($m) => $m->hasTo('awa@exemple.ci'));
    Mail::assertQueued(BonDeMiseADispositionMail::class);
    expect(codeRecuParLeClient())->toMatch('/^\d{6}$/');
});

it('envoie aussi la confirmation par SMS et WhatsApp quand le client a un téléphone, sans jamais y mettre le code', function (): void {
    $this->client->update(['telephone' => '+2250700112233']);
    encaisserReellement($this->sejour->acompte_exige);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);

    $notifications = Notification::where('destinataire', '+2250700112233')->get();
    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('canal.value')->sort()->values()->all())->toBe(['sms', 'whatsapp'])
        ->and($notifications->every(fn ($n) => $n->modele->value === 'confirmation'))->toBeTrue()
        ->and($notifications->every(fn ($n) => $n->etat->value === 'envoyee'))->toBeTrue()
        ->and($notifications->every(fn ($n) => ! str_contains($n->corps, codeRecuParLeClient())))->toBeTrue();
});

it('ne tente aucun SMS ni WhatsApp pour un client sans téléphone', function (): void {
    encaisserReellement($this->sejour->acompte_exige);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);

    expect(Notification::count())->toBe(0);
});

it('valide d’office le bon quand le mandat le prévoit, et n’écrit pas au compte interne', function (): void {
    $this->sejour->logement->residence->proprietaire->update(['bons_valides_automatiquement' => true]);
    encaisserReellement($this->sejour->acompte_exige);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);

    expect(BonDeMiseADisposition::sole()->etat)->toBe('valide');
});

it('refuse de confirmer si la résidence a été fermée entre-temps, ou avec un agent qui n’en est pas un', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    $confirmation = app(ConfirmationDeSejour::class);

    expect(fn () => $confirmation->confirmer($this->sejour, $this->gestionnaire, $this->client->id))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('agent_invalide'));

    $this->sejour->logement->residence->update(['disponibilite' => Disponibilite::Occupee]);
    expect(fn () => $confirmation->confirmer($this->sejour->refresh(), $this->gestionnaire))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('confirmation_impossible'));
});

it('confirme même si l’envoi des courriels échoue', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('serveur de messagerie en panne'));

    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme)
        ->and(app(CodesSecrets::class)->existe($this->sejour, 'arrivee'))->toBeTrue();
});

// ---------------------------------------------------------------- le code : chez le client, jamais chez l'agent

it('ne laisse JAMAIS le code d’arrivée sortir vers le personnel, par aucune route', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);
    $code = codeRecuParLeClient();
    $id = $this->sejour->id;

    $routes = [
        "/api/v1/backoffice/sejours/{$id}", '/api/v1/backoffice/sejours', '/api/v1/backoffice/sejours?etat=confirme',
        '/api/v1/backoffice/audit?par_page=100', "/api/v1/backoffice/caisse/clients/{$this->client->id}/affaires", '/api/v1/backoffice/caisse/reglements',
    ];

    foreach ([$this->gestionnaire, $this->admins[0], User::factory()->profil(Profil::SuperAdministrateur)->create()] as $membre) {
        comme($membre);
        foreach ($routes as $route) {
            $reponse = test()->getJson($route);
            if ($reponse->status() === 200) {
                expect($reponse->getContent())->not->toContain($code);
            }
        }
    }

    // Ni en clair dans la base, ni dans le journal d'audit.
    expect(DB::table('codes_secrets')->value('code'))->not->toContain($code)
        ->and(EntreeAudit::all()->toJson())->not->toContain($code);
});

it('montre le code au client titulaire, une fois confirmé, et à lui seul', function (): void {
    comme($this->client);
    $adresse = "/api/v1/client/sejours/{$this->sejour->reference}";
    test()->getJson($adresse)->assertOk()->assertJsonPath('data.code_d_arrivee', null);

    encaisserReellement($this->sejour->acompte_exige);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);
    $code = codeRecuParLeClient();

    comme($this->client);
    test()->getJson($adresse)->assertOk()->assertJsonPath('data.code_d_arrivee', $code)->assertJsonPath('data.acces.adresse', 'Rue des Jardins, lot 45');

    comme(User::factory()->create());   // un autre client
    test()->getJson($adresse)->assertNotFound();
});

it('laisse le gestionnaire renvoyer le code sans le voir, et l’administrateur en émettre un nouveau', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);
    $ancien = codeRecuParLeClient();
    $base = "/api/v1/backoffice/sejours/{$this->sejour->id}/code-arrivee";

    comme($this->gestionnaire);
    $renvoi = test()->postJson("{$base}/renvoi")->assertOk();
    expect($renvoi->getContent())->not->toContain($ancien);
    Mail::assertQueuedCount(4);     // reçu de l’acompte + confirmation + bon au propriétaire + renvoi du code

    test()->postJson("{$base}/nouveau")->assertForbidden();     // réservé aux administrateurs

    comme($this->admins[0]);
    $nouveau = test()->postJson("{$base}/nouveau")->assertOk();
    $code = app(CodesSecrets::class)->lirePourLeClient($this->sejour, 'arrivee');
    expect($code)->not->toBe($ancien)->and($nouveau->getContent())->not->toContain((string) $code);
});

it('vérifie le code saisi par l’agent : juste une fois, faux cinq fois, puis verrouillé', function (): void {
    $codes = app(CodesSecrets::class);
    $agent = User::factory()->profil(Profil::AgentTerrain)->create();
    $bon = $codes->generer($this->sejour, 'arrivee');
    $faux = $bon === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $essai) {
        expect(fn () => $codes->verifier($this->sejour, 'arrivee', $faux, $agent))
            ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_incorrect'));
    }
    expect(fn () => $codes->verifier($this->sejour, 'arrivee', $faux, $agent))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_verrouille'));

    // Même le BON code ne passe plus : seul un nouveau code, envoyé au client, débloque.
    expect(fn () => $codes->verifier($this->sejour, 'arrivee', $bon, $agent))->toThrow(ErreurMetier::class)
        ->and(EntreeAudit::where('action', 'code_verrouille')->count())->toBe(1);

    $nouveau = $codes->generer($this->sejour, 'arrivee');
    $codes->verifier($this->sejour, 'arrivee', $nouveau, $agent);
    expect(fn () => $codes->verifier($this->sejour, 'arrivee', $nouveau, $agent))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_deja_utilise'));
});

// ---------------------------------------------------------------- expiration

it('ne fait JAMAIS expirer la demande d’un client qui a payé, même si le circuit de preuve n’est pas fini', function (): void {
    encaisserReellement(10000, jusquAuBout: false);

    Carbon::setTestNow('2026-10-05 10:00:00');       // bien après les 24 heures
    $this->artisan('sejours:expirer-demandes')->assertSuccessful();

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Demande);
});

// ---------------------------------------------------------------- écran « Réservations »

it('liste les réservations en attente avec ce qui empêche de les confirmer', function (): void {
    comme($this->gestionnaire);

    $element = test()->getJson('/api/v1/backoffice/sejours?etat=demande&du=2026-11-01&au=2026-11-30')->assertOk()->json('data.elements.0');

    expect($element['reference'])->toBe($this->sejour->reference)
        ->and($element['reglement'])->toMatchArray(['encaisse' => 0, 'acompte_atteint' => false])
        ->and($element['obstacles_a_la_confirmation'][0])->toContain('acompte')
        ->and($element['code_d_arrivee_emis'])->toBeFalse();
});

it('ferme les réservations du back office aux autres profils', function (Profil $profil): void {
    comme(User::factory()->profil($profil)->create());

    test()->getJson('/api/v1/backoffice/sejours')->assertForbidden();
    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/confirmation")->assertForbidden();
})->with([Profil::Client, Profil::Proprietaire, Profil::AgentTerrain]);

it('ne donne au propriétaire ni le client, ni le prix de vente, ni le code', function (): void {
    encaisserReellement($this->sejour->acompte_exige);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour, $this->gestionnaire);

    $rendu = (new BonDeMiseADispositionMail($this->sejour->refresh()))->render();

    expect($rendu)->not->toContain(codeRecuParLeClient())->not->toContain('awa@exemple.ci')->not->toContain('30 000')->not->toContain('30000');
});

it('n’a rien à demander au propriétaire interne de l’entreprise', function (): void {
    $this->sejour->logement->residence->update(['proprietaire_id' => Proprietaire::factory()->interne()->create()->id]);
    encaisserReellement($this->sejour->acompte_exige);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);

    Mail::assertNotQueued(BonDeMiseADispositionMail::class);
    expect(BonDeMiseADisposition::sole()->etat)->toBe('valide');
});
