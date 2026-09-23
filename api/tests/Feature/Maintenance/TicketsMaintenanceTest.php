<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Models\TicketMaintenance;
use App\Domain\Sejours\Services\Calendrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $this->admin = User::factory()->profil(Profil::Administrateur)->create();

    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
});

function connecteTicket(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('signale un ticket bloquant et retire le logement du calendrier', function (): void {
    connecteTicket($this->admin);
    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-13')))->toBeTrue();

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'bloquante',
        'description' => 'Fuite d’eau importante dans la salle de bain',
        'indisponible_jusquau' => '2026-11-15',
    ])->assertCreated();

    expect($reponse->json('data.urgence'))->toBe('bloquante')
        ->and($reponse->json('data.statut'))->toBe('ouvert');

    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-13')))->toBeFalse();

    $ticket = TicketMaintenance::first();
    expect($ticket->blocage_calendrier_id)->not->toBeNull();
});

it('un ticket bloquant sans date d’indisponibilité est refusé', function (): void {
    connecteTicket($this->admin);

    test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'bloquante',
        'description' => 'Panne électrique générale',
    ])->assertStatus(422);
});

it('un ticket non bloquant ne touche pas le calendrier', function (): void {
    connecteTicket($this->admin);

    test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'normale',
        'description' => 'Ampoule à changer dans la cuisine',
    ])->assertCreated();

    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-13')))->toBeTrue();
});

it('la résolution du ticket lève le blocage : le logement redevient réservable', function (): void {
    connecteTicket($this->admin);

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'bloquante',
        'description' => 'Climatisation en panne',
        'indisponible_jusquau' => '2026-11-20',
    ])->assertCreated();
    $ticketId = $reponse->json('data.id');

    test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance/{$ticketId}/resolution")
        ->assertOk()->assertJsonPath('data.statut', 'resolu');

    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-13')))->toBeTrue();
});

it('impute un coût au propriétaire ou à l’entreprise', function (): void {
    connecteTicket($this->admin);

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'normale', 'description' => 'Remplacement d’une ampoule',
    ])->assertCreated();
    $ticketId = $reponse->json('data.id');

    test()->putJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance/{$ticketId}/cout", [
        'montant' => 5000, 'impute_a' => 'proprietaire',
    ])->assertOk()->assertJsonPath('data.cout_montant', 5000)->assertJsonPath('data.cout_impute_a', 'proprietaire');
});

it('affecte un technicien, en texte libre — aucun profil dédié', function (): void {
    connecteTicket($this->admin);

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance", [
        'urgence' => 'haute', 'description' => 'Porte d’entrée bloquée',
    ])->assertCreated();
    $ticketId = $reponse->json('data.id');

    test()->putJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/tickets-maintenance/{$ticketId}/technicien", [
        'technicien_nom' => 'Koffi Serrurerie', 'technicien_contact' => '+225 07 00 00 00',
    ])->assertOk()->assertJsonPath('data.technicien_nom', 'Koffi Serrurerie');
});
