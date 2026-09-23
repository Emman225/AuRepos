<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

it('exporte les écritures comptables d’une facture transmise au format Sage (CSV)', function (): void {
    $client = User::factory()->create();
    $sejour = Sejour::factory()->create([
        'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
        'devis' => ['hebergement_net_ht' => 90000, 'extras_ht' => 0, 'transfert_ht' => 0, 'total_tva' => 16200, 'tdt' => 900, 'taxe_de_sejour' => 3000],
        'net_a_payer' => 110100,
    ]);
    Facture::create([
        'numero' => 'FAC-000001', 'type' => 'facture', 'sejour_id' => $sejour->id, 'client_id' => $client->id,
        'montant_ht' => 90000, 'montant_tva' => 16200, 'autres_taxes' => 3900, 'montant_ttc' => 110100,
        'lignes' => [], 'statut_transmission' => 'transmise', 'transmise_le' => '2026-11-05 10:00:00',
        'genere_par' => User::factory()->create()->id,
    ]);

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    $reponse = test()->get('/api/v1/backoffice/comptabilite/export?du=2026-11-01&au=2026-11-30&format=sage')->assertOk();
    $corps = $reponse->getContent();

    expect($corps)->toContain('706100') // compte hébergement
        ->toContain('90000')
        ->toContain('445700') // compte TVA collectée
        ->toContain('16200')
        ->toContain('411000') // compte clients
        ->toContain('110100');
});

it('exporte les mêmes écritures au format Excel', function (): void {
    $client = User::factory()->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'devis' => ['hebergement_net_ht' => 50000], 'net_a_payer' => 50000]);
    Facture::create([
        'numero' => 'FAC-000002', 'type' => 'facture', 'sejour_id' => $sejour->id, 'client_id' => $client->id,
        'montant_ht' => 50000, 'montant_tva' => 0, 'autres_taxes' => 0, 'montant_ttc' => 50000,
        'lignes' => [], 'statut_transmission' => 'transmise', 'transmise_le' => '2026-11-05 10:00:00',
        'genere_par' => User::factory()->create()->id,
    ]);

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    test()->get('/api/v1/backoffice/comptabilite/export?du=2026-11-01&au=2026-11-30&format=xlsx')->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
