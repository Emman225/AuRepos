<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Services\CheckIn;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    Mail::fake();
    Storage::fake('local');

    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->gestionnaire = $personnel(Profil::Gestionnaire);
    $this->admins = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->agentTerrain = User::factory()->profil(Profil::AgentTerrain)->create();
    $this->agentAssistance = User::factory()->profil(Profil::AgentAssistance)->create();

    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$this->sejour->id], $this->sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);
});

function connecteAssistance(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('refuse un ticket sur un séjour pas encore arrivé', function (): void {
    connecteAssistance($this->client);

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/tickets-assistance", [
        'sujet' => 'Climatisation', 'message' => 'La climatisation ne fonctionne pas',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_arrive');
});

it('le client soulève un ticket pendant un séjour arrivé, l’espace assistance répond puis ferme', function (): void {
    $code = app(CodesSecrets::class)->lirePourLeClient($this->sejour->refresh(), 'arrivee');
    app(CheckIn::class)->effectuer($this->sejour->refresh(), $code, $this->agentTerrain);

    connecteAssistance($this->client);
    $ticket = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/tickets-assistance", [
        'sujet' => 'Climatisation', 'message' => 'La climatisation ne fonctionne pas',
    ])->assertCreated()->assertJsonPath('data.statut', 'ouvert')->json('data');

    connecteAssistance($this->agentAssistance);
    test()->getJson('/api/v1/assistance/tickets')->assertOk()->assertJsonCount(1, 'data');

    test()->postJson("/api/v1/assistance/tickets/{$ticket['id']}/reponse", ['reponse' => 'Un technicien passe dans l’heure'])
        ->assertOk()->assertJsonPath('data.statut', 'en_cours');

    test()->postJson("/api/v1/assistance/tickets/{$ticket['id']}/fermeture", ['reponse' => 'Résolu'])
        ->assertOk()->assertJsonPath('data.statut', 'ferme');

    // Une fois fermé, il sort de la file par défaut (ouverts + en cours seulement).
    test()->getJson('/api/v1/assistance/tickets')->assertOk()->assertJsonCount(0, 'data');
});

it('ferme l’espace assistance aux autres profils, y compris l’agent de terrain', function (): void {
    connecteAssistance($this->agentTerrain);
    test()->getJson('/api/v1/assistance/tickets')->assertForbidden();
});

it('un client ne voit pas les tickets d’un séjour qui n’est pas le sien', function (): void {
    $autreClient = User::factory()->create();
    connecteAssistance($autreClient);

    test()->getJson("/api/v1/client/sejours/{$this->sejour->reference}/tickets-assistance")->assertNotFound();
});
