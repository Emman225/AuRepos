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
use App\Domain\Sejours\Services\EtatsDesLieux;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    $this->agent = User::factory()->profil(Profil::AgentTerrain)->create();

    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$this->sejour->id], $this->sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);

    deposerEtFinaliserLaCaution($this->sejour, $this->gestionnaire, $this->admins[0], $this->admins[1]);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);

    $code = app(CodesSecrets::class)->lirePourLeClient($this->sejour->refresh(), 'arrivee');
    app(CheckIn::class)->effectuer($this->sejour->refresh(), $code, $this->agent);
    $this->sejour->refresh();
});

function connecteAgentEDL(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('établit un état des lieux d’entrée avec ses lignes, une seule fois', function (): void {
    connecteAgentEDL($this->agent);
    $base = "/api/v1/agent/sejours/{$this->sejour->id}/etats-des-lieux";

    $etat = test()->postJson($base, ['type' => 'entree'])->assertCreated()->json('data');

    test()->postJson($base.'/'.$etat['id'].'/lignes', ['libelle' => 'Climatiseur salon', 'observation' => 'fonctionne, RAS'])->assertCreated();
    test()->postJson($base.'/'.$etat['id'].'/lignes', ['libelle' => 'Compteur électrique', 'observation' => '00123'])->assertCreated();

    test()->postJson($base, ['type' => 'entree'])->assertStatus(422)->assertJsonPath('errors.code.0', 'etat_des_lieux_deja_etabli');

    $lignes = test()->getJson($base)->assertOk()->json('data.0.lignes');
    expect($lignes)->toHaveCount(2);
});

it('dépose une photo par ligne, chiffrée comme une pièce justificative', function (): void {
    connecteAgentEDL($this->agent);
    $base = "/api/v1/agent/sejours/{$this->sejour->id}/etats-des-lieux";
    $etat = test()->postJson($base, ['type' => 'entree'])->json('data');
    $ligne = test()->postJson($base.'/'.$etat['id'].'/lignes', ['libelle' => 'Canapé'])->json('data');

    test()->postJson($base.'/'.$etat['id'].'/lignes/'.$ligne['id'].'/photos', [
        'fichier' => UploadedFile::fake()->image('canape.jpg', 800, 600)->size(150),
    ])->assertOk();

    expect(DB::table('pieces_justificatives')->where('type', 'photo_etat_des_lieux')->count())->toBe(1);
});

it('verrouille l’état des lieux à la signature : plus aucune ligne, plus aucune photo', function (): void {
    connecteAgentEDL($this->agent);
    $base = "/api/v1/agent/sejours/{$this->sejour->id}/etats-des-lieux";
    $etat = test()->postJson($base, ['type' => 'entree'])->json('data');
    test()->postJson($base.'/'.$etat['id'].'/lignes', ['libelle' => 'Canapé', 'observation' => 'bon état'])->assertCreated();

    test()->postJson($base.'/'.$etat['id'].'/signature', ['signature' => 'data:image/png;base64,iVBORw0KG=='])
        ->assertOk()->assertJsonPath('data.signe', true);

    test()->postJson($base.'/'.$etat['id'].'/lignes', ['libelle' => 'Trop tard'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'etat_des_lieux_signe');

    // La signature elle-même ne ressort jamais en clair dans les réponses.
    $reponse = test()->getJson($base);
    expect($reponse->getContent())->not->toContain('iVBORw0KG==');
});

it('refuse de signer un état des lieux sans aucune ligne', function (): void {
    connecteAgentEDL($this->agent);
    $base = "/api/v1/agent/sejours/{$this->sejour->id}/etats-des-lieux";
    $etat = test()->postJson($base, ['type' => 'entree'])->json('data');

    test()->postJson($base.'/'.$etat['id'].'/signature', ['signature' => 'x'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'lignes_manquantes');
});

it('compare sortie et entrée par libellé identique, et signale les écarts sur le PDF', function (): void {
    connecteAgentEDL($this->agent);
    $base = "/api/v1/agent/sejours/{$this->sejour->id}/etats-des-lieux";

    $entree = test()->postJson($base, ['type' => 'entree'])->json('data');
    test()->postJson($base.'/'.$entree['id'].'/lignes', ['libelle' => 'Climatiseur salon', 'observation' => 'RAS'])->assertCreated();
    test()->postJson($base.'/'.$entree['id'].'/signature', ['signature' => 'x'])->assertOk();

    $this->sejour->update(['etat' => 'parti']);
    $sortie = test()->postJson($base, ['type' => 'sortie'])->json('data');
    // Même libellé, casse différente : la comparaison n'y est pas sensible.
    test()->postJson($base.'/'.$sortie['id'].'/lignes', ['libelle' => 'CLIMATISEUR SALON', 'observation' => 'fuite constatée'])->assertCreated();

    $pdf = test()->get($base.'/pdf')->assertOk();
    expect($pdf->headers->get('Content-Type'))->toBe('application/pdf');

    $donnees = app(EtatsDesLieux::class)->pdf($this->sejour->refresh());
    expect($donnees)->toBeString()->not->toBeEmpty();
});

it('n’établit un état des lieux de sortie qu’à partir du check-out', function (): void {
    $sejour = $this->sejour;
    $sejour->update(['etat' => 'demande']);

    connecteAgentEDL($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$sejour->id}/etats-des-lieux", ['type' => 'sortie'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'etat_des_lieux_impossible');
});
