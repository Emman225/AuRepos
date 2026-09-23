<?php

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

function connecteAdminGrandsLivres(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

it('additionne le reste dû d’un client ordinaire dans le grand livre « clients_ordinaires »', function (): void {
    $client = User::factory()->create();
    Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Confirme, 'net_a_payer' => 42000]);

    connecteAdminGrandsLivres();

    test()->getJson('/api/v1/backoffice/comptabilite/grands-livres/clients_ordinaires')->assertOk()
        ->assertJsonPath('data.total_du', 42000);
});

it('additionne les décaissements effectués vers les propriétaires dans le grand livre « proprietaires »', function (): void {
    $proprietaireUser = User::factory()->profil(Profil::Proprietaire)->create();
    Proprietaire::factory()->create(['user_id' => $proprietaireUser->id]);

    reglementEffectue([
        'sens' => 'decaissement', 'guichet' => 'dettes_partenaires', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => $proprietaireUser->id, 'montant' => 65000, 'mode' => 'virement', 'notes' => 'Reversement',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => now(),
    ]);

    connecteAdminGrandsLivres();

    test()->getJson('/api/v1/backoffice/comptabilite/grands-livres/proprietaires')->assertOk()
        ->assertJsonPath('data.total_verse', 65000);
});

it('refuse une catégorie de tiers inconnue', function (): void {
    connecteAdminGrandsLivres();

    test()->getJson('/api/v1/backoffice/comptabilite/grands-livres/inconnue')->assertStatus(422);
});
