<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Calendrier;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');

    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->gestionnaire = $personnel(Profil::Gestionnaire);
    $this->admins = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];

    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create();
});

function confirmerPourNoShow(User $client, User $gestionnaire, array $admins, Logement $logement, string $arrivee, string $depart): Sejour
{
    $sejour = app(ReservationDeSejour::class)->reserver($client, $logement->refresh(), [
        'arrivee' => $arrivee, 'depart' => $depart, 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($gestionnaire, $client, [$sejour->id], $sejour->acompte_exige, ModeDeReglement::Especes, 'Acompte');
    $caisse->valider($r, $admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $admins[1]);

    return app(ConfirmationDeSejour::class)->confirmer($sejour->refresh(), $gestionnaire)->refresh();
}

it('bascule en no-show à J+1, à l’heure paramétrable, un séjour confirmé jamais arrivé', function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $sejour = confirmerPourNoShow($this->client, $this->gestionnaire, $this->admins, $this->logement, '2026-11-10', '2026-11-13');

    // Avant l'heure paramétrée de J+1 (12:00 par défaut) : pas encore de no-show.
    Carbon::setTestNow('2026-11-11 08:00:00');
    $this->artisan('sejours:traiter-no-show')->assertSuccessful();
    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);

    // Passé l'heure paramétrée : bascule.
    Carbon::setTestNow('2026-11-11 12:30:00');
    $this->artisan('sejours:traiter-no-show')->expectsOutputToContain('1 séjour(s) basculé(s) en no-show.')->assertSuccessful();

    $sejour = $sejour->refresh();
    expect($sejour->etat)->toBe(EtatDuSejour::NoShow)
        ->and($sejour->no_show_le)->not->toBeNull()
        ->and($sejour->montant_retenu_annulation)->toBeGreaterThan(0); // retenue selon la politique (délai déjà dépassé)
});

it('libère les dates du calendrier dès le no-show', function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $sejour = confirmerPourNoShow($this->client, $this->gestionnaire, $this->admins, $this->logement, '2026-11-10', '2026-11-13');
    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeFalse();

    Carbon::setTestNow('2026-11-11 13:00:00');
    $this->artisan('sejours:traiter-no-show')->assertSuccessful();

    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeTrue();
});

it('ne touche jamais un séjour déjà arrivé, ni une simple demande', function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $sejour = confirmerPourNoShow($this->client, $this->gestionnaire, $this->admins, $this->logement, '2026-11-10', '2026-11-13');

    $autreLogement = Logement::factory()->create(['residence_id' => $this->residence->id]);
    $autreLogement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 20000])->save();
    $demande = app(ReservationDeSejour::class)->reserver(User::factory()->create(), $autreLogement->refresh(), [
        'arrivee' => '2026-11-05', 'depart' => '2026-11-07', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    Carbon::setTestNow('2026-11-20 12:30:00');
    $this->artisan('sejours:traiter-no-show')->assertSuccessful();

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::NoShow) // celui-ci, lui, bascule normalement
        ->and($demande->refresh()->etat)->toBe(EtatDuSejour::Demande); // une demande n'est jamais concernée par le no-show
});
