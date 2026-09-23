<?php

use App\Domain\Assistance\Enums\EtatDeLaReclamation;
use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Services\CheckIn;
use App\Domain\Sejours\Services\CheckOut;
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
    $this->tresorier = $personnel(Profil::Administrateur);
    $this->agentTerrain = User::factory()->profil(Profil::AgentTerrain)->create();

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

    deposerEtFinaliserLaCaution($this->sejour, $this->gestionnaire, $this->admins[0], $this->admins[1]);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);

    $code = app(CodesSecrets::class)->lirePourLeClient($this->sejour->refresh(), 'arrivee');
    app(CheckIn::class)->effectuer($this->sejour->refresh(), $code, $this->agentTerrain);
    app(CheckOut::class)->effectuer($this->sejour->refresh(), $this->agentTerrain, 0, null);
    $this->sejour->refresh();
});

function connecteReclamation(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

function designerLeTresorierReclamation(User $tresorier): void
{
    app(Parametres::class)->enregistrer('gestionnaires', ['validant_2_id' => $tresorier->id], test()->admins[0]);
}

// ---------------------------------------------------------------- création, motif ≥ 15 caractères

it('refuse un motif de moins de 15 caractères, exactement comme l’exige le CdC', function (): void {
    connecteReclamation($this->client);

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => 'Trop court'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['motif']]);
});

it('accepte un motif d’exactement 15 caractères', function (): void {
    connecteReclamation($this->client);
    $motif = str_repeat('a', 15);
    expect(mb_strlen($motif))->toBe(15);

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => $motif])
        ->assertCreated()->assertJsonPath('data.statut', 'ouverte');
});

it('refuse une réclamation sur un séjour pas encore terminé', function (): void {
    $autreLogement = Logement::factory()->create(['residence_id' => $this->sejour->logement->residence_id]);
    $autreLogement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 20000])->save();
    $enCours = app(ReservationDeSejour::class)->reserver($this->client, $autreLogement->refresh(), [
        'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    connecteReclamation($this->client);
    test()->postJson("/api/v1/client/sejours/{$enCours->reference}/reclamations", ['motif' => str_repeat('b', 20)])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_termine');
});

// ---------------------------------------------------------------- fermeture sans avoir

it('le back office ferme une réclamation sans rien verser', function (): void {
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('c', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/fermeture", ['reponse' => 'Non fondée, expliqué au client'])
        ->assertOk()->assertJsonPath('data.statut', 'fermee');
});

// ---------------------------------------------------------------- avoir / geste commercial : MÊME mécanisme que la réduction

it('refuse toute proposition d’avoir tant qu’aucun trésorier n’est désigné', function (): void {
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('d', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 5000, 'motif' => 'Geste commercial'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'tresorier_non_designe');
});

it('propose un avoir une fois le trésorier désigné, sans rien verser avant confirmation', function (): void {
    designerLeTresorierReclamation($this->tresorier);
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('e', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    $reponse = test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 5000, 'motif' => 'Geste commercial'])
        ->assertCreated();

    expect($reponse->json('data.champ'))->toBe('avoir_montant');
    expect(Reclamation::find($reclamation['id'])->statut)->toBe(EtatDeLaReclamation::EnCours);
    // « Sans rien verser » : la proposition d'avoir ne crée AUCUN décaissement tant que le
    // trésorier n'a pas confirmé. On compte les décaissements plutôt que tous les règlements,
    // le parcours nominal en comptant déjà deux à l'encaissement (solde du séjour + caution).
    expect(Reglement::where('sens', 'decaissement')->count())->toBe(0);
});

it('refuse qu’un administrateur QUELCONQUE confirme l’avoir : seul LE trésorier désigné le peut', function (): void {
    designerLeTresorierReclamation($this->tresorier);
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('f', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    $id = test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 5000, 'motif' => 'Geste commercial'])->json('data.id');

    $unAutreAdmin = User::factory()->profil(Profil::Administrateur)->create();
    connecteReclamation($unAutreAdmin);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider', 'mode_de_remboursement' => 'especes'])
        ->assertStatus(403)->assertJsonPath('errors.code.0', 'tresorier_requis');
});

it('exige le mode de règlement pour confirmer l’avoir', function (): void {
    designerLeTresorierReclamation($this->tresorier);
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('g', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    $id = test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 5000, 'motif' => 'Geste commercial'])->json('data.id');

    connecteReclamation($this->tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'mode_de_remboursement_obligatoire');
});

it('LE trésorier désigné confirme l’avoir : la réclamation ferme et un décaissement est saisi', function (): void {
    designerLeTresorierReclamation($this->tresorier);
    connecteReclamation($this->client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/reclamations", ['motif' => str_repeat('h', 20)])->json('data');

    connecteReclamation($this->admins[0]);
    $id = test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 7500, 'motif' => 'Geste commercial'])->json('data.id');

    connecteReclamation($this->tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider', 'mode_de_remboursement' => 'mobile_money'])
        ->assertOk();

    $ligne = Reclamation::find($reclamation['id']);
    expect($ligne->statut)->toBe(EtatDeLaReclamation::Fermee)
        ->and($ligne->avoir_montant)->toBe(7500)
        ->and($ligne->reglement_id)->not->toBeNull();

    $reglement = Reglement::find($ligne->reglement_id);
    expect($reglement->sens)->toBe('decaissement')
        ->and($reglement->guichet)->toBe(Guichet::Remboursements)
        ->and($reglement->tiers_id)->toBe($this->client->id)
        ->and($reglement->montant)->toBe(7500)
        ->and($reglement->mode)->toBe(ModeDeReglement::MobileMoney);
});
