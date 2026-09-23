<?php

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDeLaDemandeAnnulation;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\DemandeAnnulation;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-01 09:00:00');
    Mail::fake();
    Storage::fake('local');

    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->gestionnaire = $personnel(Profil::Gestionnaire);
    $this->admins = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];

    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $this->gestionnaire->residences()->attach($residence->id);

    // Arrivée dans 2 jours : la politique modérée par défaut (gratuite jusqu'à 5 jours avant) retient déjà quelque chose.
    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-03', 'depart' => '2026-11-06', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$this->sejour->id], $this->sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);
    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);
    $this->sejour->refresh();
});

function connecteAnnulation(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('le client demande, le séjour n’est PAS annulé tant que la réception n’a rien instruit', function (): void {
    connecteAnnulation($this->client);

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Changement de programme'])
        ->assertCreated()->assertJsonPath('data.etat', 'en_attente');

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);
});

it('refuse une seconde demande tant que la première est en attente', function (): void {
    connecteAnnulation($this->client);
    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Premier motif'])->assertCreated();

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Second motif'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'demande_deja_en_cours');
});

it('un administrateur qui accepte annule le séjour et rembourse le reste par décaissement', function (): void {
    connecteAnnulation($this->client);
    $demande = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Empêchement'])
        ->json('data');

    connecteAnnulation($this->admins[0]);
    $reponse = test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/acceptation", [
        'mode_de_remboursement' => 'mobile_money',
    ])->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->etat)->toBe(EtatDuSejour::Annule)
        ->and($sejour->montant_retenu_annulation)->toBeGreaterThan(0);

    $ligne = DemandeAnnulation::find($demande['id']);
    expect($ligne->etat)->toBe(EtatDeLaDemandeAnnulation::Acceptee)
        ->and($ligne->montant_retenu)->toBe($sejour->montant_retenu_annulation)
        ->and($ligne->montant_rembourse)->toBe($sejour->net_a_payer - $sejour->montant_retenu_annulation);

    if ($ligne->montant_rembourse > 0) {
        $reglement = Reglement::find($ligne->reglement_id);
        expect($reglement->sens)->toBe('decaissement')
            ->and($reglement->guichet)->toBe(Guichet::Remboursements)
            ->and($reglement->tiers_id)->toBe($this->client->id)
            ->and($reglement->montant)->toBe($ligne->montant_rembourse);
    }
});

it('exige le mode de remboursement quand un montant reste à rembourser', function (): void {
    connecteAnnulation($this->client);
    $demande = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Empêchement'])->json('data');

    connecteAnnulation($this->admins[0]);
    test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/acceptation", [])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'mode_de_remboursement_obligatoire');

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);
});

it('un administrateur qui rejette laisse le séjour inchangé', function (): void {
    connecteAnnulation($this->client);
    $demande = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Empêchement'])->json('data');

    connecteAnnulation($this->admins[0]);
    test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/rejet", ['motif' => 'Le CdC ne permet pas d’annuler si tard'])
        ->assertOk()->assertJsonPath('data.etat', 'rejetee');

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);
});

it('ne s’instruit jamais deux fois', function (): void {
    connecteAnnulation($this->client);
    $demande = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Empêchement'])->json('data');

    connecteAnnulation($this->admins[0]);
    test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/rejet", ['motif' => 'Trop tard'])->assertOk();

    test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/acceptation", ['mode_de_remboursement' => 'especes'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'demande_deja_instruite');
});

it('réserve l’instruction aux administrateurs, jamais un gestionnaire', function (): void {
    connecteAnnulation($this->client);
    $demande = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/demande-annulation", ['motif' => 'Empêchement'])->json('data');

    connecteAnnulation($this->gestionnaire);
    test()->postJson("/api/v1/backoffice/demandes-annulation/{$demande['id']}/rejet", ['motif' => 'Pas mon rôle'])->assertForbidden();
});

it('refuse une demande sur une simple demande de séjour : le client l’annule déjà lui-même', function (): void {
    $autreLogement = Logement::factory()->create(['residence_id' => $this->sejour->logement->residence_id]);
    $autreLogement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 20000])->save();
    $simpleDemande = app(ReservationDeSejour::class)->reserver($this->client, $autreLogement->refresh(), [
        'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    connecteAnnulation($this->client);
    test()->postJson("/api/v1/client/sejours/{$simpleDemande->reference}/demande-annulation", ['motif' => 'Changement de plans'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_annulable');
});
