<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\CheckOut;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agence = Agence::factory()->create();
    $this->gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => $this->agence->id]);
    $this->client = User::factory()->create();
    $this->extra = Extra::factory()->create(['nom' => 'Late check-out', 'prix' => 10000, 'actif' => true]);
});

function connecteCommeExtra(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

// ---------------------------------------------------------------- la garde « séjour arrivé »

it('refuse une commande d’extra sur un séjour pas encore arrivé', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'etat' => EtatDuSejour::Confirme]);
    connecteCommeExtra($this->client);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/extras", ['extra_id' => $this->extra->id])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_arrive');

    expect(CommandeExtra::count())->toBe(0);
});

it('le client commande un extra pendant son séjour arrivé, prix et nom figés au moment T', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'etat' => EtatDuSejour::Arrive]);
    connecteCommeExtra($this->client);

    $reponse = test()->postJson("/api/v1/client/sejours/{$sejour->reference}/extras", [
        'extra_id' => $this->extra->id, 'quantite' => 2, 'notes' => 'Départ à 16h si possible',
    ])->assertCreated();

    expect($reponse->json('data'))->toMatchArray(['nom_extra' => 'Late check-out', 'prix_unitaire' => 10000, 'quantite' => 2, 'montant_total' => 20000, 'etat' => 'demande']);

    // Renommer et re-tarifer l'extra ensuite ne change jamais la commande déjà passée.
    $this->extra->update(['nom' => 'Late check-out (renommé)', 'prix' => 99999]);
    expect(CommandeExtra::first()->fresh()->montant_total)->toBe(20000);
});

it('refuse un extra désactivé, et une commande d’un séjour qui n’est pas le sien (404, jamais 403)', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'etat' => EtatDuSejour::Arrive]);
    $this->extra->update(['actif' => false]);
    connecteCommeExtra($this->client);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/extras", ['extra_id' => $this->extra->id])->assertNotFound();

    $autreClient = User::factory()->create();
    connecteCommeExtra($autreClient);
    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/extras", ['extra_id' => $this->extra->id])->assertNotFound();
});

// ---------------------------------------------------------------- staff : commande au nom du client, cycle

it('la réception commande un extra au nom du client (guichet, téléphone)', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'etat' => EtatDuSejour::Arrive]);
    connecteCommeExtra($this->gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/commandes-extras', [
        'sejour_id' => $sejour->id, 'extra_id' => $this->extra->id, 'quantite' => 1,
    ])->assertCreated();

    expect($reponse->json('data.demande_par'))->toBe($this->gestionnaire->nomComplet());
});

it('déroule commande → confirmation → affectation → service rendu', function (): void {
    $sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'etat' => EtatDuSejour::Arrive]);
    $commande = CommandeExtra::factory()->create([
        'sejour_id' => $sejour->id, 'extra_id' => $this->extra->id, 'nom_extra' => 'Late check-out', 'montant_total' => 10000,
    ]);
    $agentTerrain = User::factory()->profil(Profil::AgentTerrain)->create();
    connecteCommeExtra($this->gestionnaire);

    test()->postJson("/api/v1/backoffice/commandes-extras/{$commande->id}/confirmer")->assertOk()->assertJsonPath('data.etat', 'confirmee');

    test()->postJson("/api/v1/backoffice/commandes-extras/{$commande->id}/affecter", ['membre_id' => $agentTerrain->id])
        ->assertOk()->assertJsonPath('data.affecte_a', $agentTerrain->nomComplet());

    $reponse = test()->postJson("/api/v1/backoffice/commandes-extras/{$commande->id}/service")->assertOk();
    expect($reponse->json('data.etat'))->toBe('fournie')->and($reponse->json('data.fournie_le'))->not->toBeNull();
});

it('refuse de sauter une étape (service avant confirmation)', function (): void {
    $commande = CommandeExtra::factory()->create();
    connecteCommeExtra($this->gestionnaire);

    test()->postJson("/api/v1/backoffice/commandes-extras/{$commande->id}/service")
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'etape_hors_sequence');
});

it('refuse une commande, motivé', function (): void {
    $commande = CommandeExtra::factory()->create();
    connecteCommeExtra($this->gestionnaire);

    test()->postJson("/api/v1/backoffice/commandes-extras/{$commande->id}/refuser", ['motif' => 'Indisponible ce jour'])
        ->assertOk()->assertJsonPath('data.etat', 'refusee');
});

// ---------------------------------------------------------------- facturation : consommations du séjour

it('compte l’extra confirmé dans les consommations du séjour, jamais dans le net à payer figé', function (): void {
    $sejour = Sejour::factory()->create(['net_a_payer' => 50000]);
    CommandeExtra::factory()->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommandeExtra::Confirmee, 'montant_total' => 10000]);
    CommandeExtra::factory()->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommandeExtra::Fournie, 'montant_total' => 5000]);
    // Ni demandée (pas encore vérifiée), ni annulée/refusée : jamais comptées.
    CommandeExtra::factory()->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommandeExtra::Demande, 'montant_total' => 7000]);
    CommandeExtra::factory()->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommandeExtra::Annulee, 'montant_total' => 3000]);

    $consommations = app(CheckOut::class)->consommations($sejour->refresh());

    expect($consommations)->toMatchArray(['hebergement' => 50000, 'extras' => 15000, 'total' => 65000]);
});
