<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Devis;
use App\Domain\Sejours\Services\Calendrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->logement = Logement::factory()->create();
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $this->client = User::factory()->create();
    test()->withToken(auth('api')->login($this->client));
});

function connecteAutreClient(): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $u = User::factory()->create();
    test()->withToken(auth('api')->login($u));

    return $u;
}

// ---------------------------------------------------------------- établissement

it('établit un devis à prix figés, sans occuper le calendrier', function (): void {
    $reponse = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->assertCreated();

    expect($reponse->json('data.reference'))->toMatch('/^DEV-\d{6}$/')
        ->and($reponse->json('data.etat'))->toBe('en_attente')
        ->and($reponse->json('data.net_a_payer'))->toBe(109386) // 3 nuits à 30 000 F, cas A du moteur
        ->and(Devis::sole()->etat)->toBe('en_attente');

    // Aucune occupation : les dates restent parfaitement libres pour tout le monde.
    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeTrue();
});

it('refuse un devis sur un logement fermé', function (): void {
    $this->logement->forceFill(['etat_publication' => EtatPublication::Brouillon])->save();

    test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'logement_non_reservable');
});

// ---------------------------------------------------------------- consultation

it('liste mes devis, et seulement les miens', function (): void {
    test()->postJson('/api/v1/client/devis', ['reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2])->assertCreated();

    connecteAutreClient();
    test()->getJson('/api/v1/client/devis')->assertOk()->assertJsonCount(0, 'data.elements');
});

it('répond 404 — jamais 403 — sur le devis d’un autre client', function (): void {
    $reponse = test()->postJson('/api/v1/client/devis', ['reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2])->assertCreated();
    $reference = $reponse->json('data.reference');

    connecteAutreClient();
    test()->getJson("/api/v1/client/devis/{$reference}")->assertNotFound();
});

// ---------------------------------------------------------------- transformation

it('transforme un devis en réservation d’un clic, avec le PRIX FIGÉ — même si la grille a changé depuis', function (): void {
    $reference = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->json('data.reference');

    // Le prix de vente change APRÈS l'établissement du devis : le devis n'en sait rien.
    $this->logement->forceFill(['prix_vente' => 50000])->save();

    $reponse = test()->postJson("/api/v1/client/devis/{$reference}/transformation", ['mode_reglement' => 'agence'])
        ->assertCreated();

    // Toujours 109 386 F (3 nuits à 30 000 F) : PAS le nouveau prix de vente à 50 000 F.
    expect($reponse->json('data.net_a_payer'))->toBe(109386)
        ->and(Devis::sole()->etat)->toBe('transforme')
        ->and(Devis::sole()->sejour_id)->not->toBeNull();

    // Cette fois, les dates sont bien prises.
    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeFalse();
});

it('refuse de transformer deux fois le même devis', function (): void {
    $reference = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->json('data.reference');

    test()->postJson("/api/v1/client/devis/{$reference}/transformation", ['mode_reglement' => 'agence'])->assertCreated();
    test()->postJson("/api/v1/client/devis/{$reference}/transformation", ['mode_reglement' => 'agence'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'devis_non_disponible');
});

it('refuse de transformer un devis dont les dates ont été prises entre-temps par un autre séjour', function (): void {
    $reference = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->json('data.reference');

    // Un autre client prend les mêmes dates avant la transformation.
    connecteAutreClient();
    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 1, 'mode_reglement' => 'agence',
    ])->assertCreated();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($this->client));

    test()->postJson("/api/v1/client/devis/{$reference}/transformation", ['mode_reglement' => 'agence'])
        ->assertStatus(409)->assertJsonPath('errors.code.0', 'dates_indisponibles');
});

// ---------------------------------------------------------------- archivage (« suppression »)

it('archive un devis en attente : il disparaît de la liste active mais n’est jamais effacé', function (): void {
    $reference = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->json('data.reference');

    test()->deleteJson("/api/v1/client/devis/{$reference}")->assertOk()->assertJsonPath('data.etat', 'archive');

    expect(Devis::sole()->etat)->toBe('archive'); // la ligne existe toujours
});

it('refuse d’archiver un devis déjà transformé', function (): void {
    $reference = test()->postJson('/api/v1/client/devis', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2,
    ])->json('data.reference');
    test()->postJson("/api/v1/client/devis/{$reference}/transformation", ['mode_reglement' => 'agence'])->assertCreated();

    test()->deleteJson("/api/v1/client/devis/{$reference}")
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'devis_non_disponible');
});

// ---------------------------------------------------------------- back-office (Direction)

it('liste les devis en attente pour la direction', function (): void {
    test()->postJson('/api/v1/client/devis', ['reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2])->assertCreated();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    test()->getJson('/api/v1/backoffice/devis')->assertOk()->assertJsonCount(1, 'data.elements')
        ->assertJsonPath('data.elements.0.etat', 'en_attente');
});

it('exporte la liste des devis en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    test()->postJson('/api/v1/client/devis', ['reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2])->assertCreated();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    $reponse = test()->getJson('/api/v1/backoffice/devis/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);
