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

    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    // Le gestionnaire du circuit n'agit que sur SES résidences (CdC § 9.5) — cf. ConfirmationTest.
    $this->gestionnaire->residences()->attach($this->residence->id);

    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $this->logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$this->sejour->id], $this->sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);

    deposerEtFinaliserLaCaution($this->sejour, $this->gestionnaire, $this->admins[0], $this->admins[1]);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);

    $agent = User::factory()->profil(Profil::AgentTerrain)->create();
    $code = app(CodesSecrets::class)->lirePourLeClient($this->sejour->refresh(), 'arrivee');
    app(CheckIn::class)->effectuer($this->sejour->refresh(), $code, $agent);
    $this->sejour->refresh();
});

function connecteGestionnaireProlong(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('prolonge un séjour arrivé : devis recalculé sur les nuitées réellement consommées, net à payer ajusté', function (): void {
    $ancienNet = $this->sejour->net_a_payer; // 3 nuits

    connecteGestionnaireProlong($this->gestionnaire);
    $reponse = test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/depart", ['depart' => '2026-11-15'])
        ->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->depart->toDateString())->toBe('2026-11-15')
        ->and($sejour->nombreDeNuits())->toBe(5)
        ->and($sejour->net_a_payer)->toBeGreaterThan($ancienNet)
        ->and($sejour->devis['nombre_de_nuits'])->toBe(5);
});

it('refuse la prolongation si les nouvelles dates chevauchent un autre séjour du même logement', function (): void {
    // Un autre séjour occupe déjà le logement juste après (une simple demande occupe déjà le calendrier).
    $autreClient = User::factory()->create();
    app(ReservationDeSejour::class)->reserver($autreClient, $this->logement->refresh(), [
        'arrivee' => '2026-11-14', 'depart' => '2026-11-16', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    connecteGestionnaireProlong($this->gestionnaire);
    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/depart", ['depart' => '2026-11-15'])
        ->assertStatus(409)->assertJsonPath('errors.code.0', 'dates_indisponibles');
});

it('permet un départ anticipé : recalcul à la baisse sur les nuitées réellement consommées', function (): void {
    connecteGestionnaireProlong($this->gestionnaire);
    $reponse = test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/depart", ['depart' => '2026-11-11'])
        ->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->nombreDeNuits())->toBe(1)
        ->and($sejour->net_a_payer)->toBeLessThan(30000 * 3);
});

it('refuse de prolonger un séjour qui n’est pas encore arrivé', function (): void {
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $this->logement->refresh(), [
        'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    connecteGestionnaireProlong($this->gestionnaire);
    test()->putJson("/api/v1/backoffice/sejours/{$sejour->id}/depart", ['depart' => '2026-12-05'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_arrive');
});

it('refuse un départ ramené avant l’arrivée, ou identique à l’actuel', function (): void {
    connecteGestionnaireProlong($this->gestionnaire);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/depart", ['depart' => '2026-11-13'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'date_inchangee');

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/depart", ['depart' => '2026-11-09'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'periode_invalide');
});
