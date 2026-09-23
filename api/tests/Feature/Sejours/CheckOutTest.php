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
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Sejours\Enums\EtatDuSejour;
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
    $this->agent = User::factory()->profil(Profil::AgentTerrain)->create();

    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000, 'caution' => 15000])->save();
    $this->gestionnaire->residences()->attach($residence->id);

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

function connecteAgentCheckout(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('effectue le check-out sans retenue et émet la facture unique du séjour', function (): void {
    connecteAgentCheckout($this->agent);

    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-out", ['caution_retenue' => 0])
        ->assertOk()->assertJsonPath('data.etat', 'parti');

    $sejour = $this->sejour->refresh();
    expect($sejour->etat)->toBe(EtatDuSejour::Parti)
        ->and($sejour->parti_le)->not->toBeNull()
        ->and($sejour->checkout_par)->toBe($this->agent->id)
        ->and($sejour->caution_retenue)->toBe(0);

    // « Un séjour, une facture » : une seule facture de type FACTURE (en plus de la proforma d'origine).
    expect(Facture::where('sejour_id', $sejour->id)->where('type', TypeDeFacture::Facture->value)->count())->toBe(1)
        ->and(Facture::where('sejour_id', $sejour->id)->where('type', TypeDeFacture::Proforma->value)->count())->toBe(1);
});

it('exige un motif pour toute retenue sur la caution, jamais un calcul automatique', function (): void {
    connecteAgentCheckout($this->agent);

    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-out", ['caution_retenue' => 5000])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'motif_obligatoire');

    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-out", [
        'caution_retenue' => 5000, 'motif' => 'Verre cassé dans la cuisine, remplacement facturé',
    ])->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->caution_retenue)->toBe(5000)
        ->and($sejour->caution_retenue_motif)->toBe('Verre cassé dans la cuisine, remplacement facturé');
});

it('refuse une retenue supérieure à la caution encaissée', function (): void {
    connecteAgentCheckout($this->agent);

    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-out", ['caution_retenue' => 999999, 'motif' => 'Dégât important'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'caution_invalide');
});

it('affiche les consommations déjà connues, séparées du net à payer du séjour', function (): void {
    connecteAgentCheckout($this->agent);

    $reponse = test()->getJson("/api/v1/agent/sejours/{$this->sejour->id}/consommations")->assertOk();

    expect($reponse->json('data'))->toMatchArray([
        'hebergement' => $this->sejour->net_a_payer, 'repas' => 0, 'transferts' => 0, 'total' => $this->sejour->net_a_payer,
    ]);
});

it('refuse un check-out sur un séjour qui n’est pas arrivé', function (): void {
    $this->sejour->update(['etat' => EtatDuSejour::Confirme]);

    connecteAgentCheckout($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-out", ['caution_retenue' => 0])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'checkout_impossible');
});
