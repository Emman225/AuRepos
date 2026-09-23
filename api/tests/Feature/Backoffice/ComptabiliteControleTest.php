<?php

use App\Domain\Caisse\Models\Imputation;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecteAdminControle(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

it('ne signale rien sur un jeu de séjours propres', function (): void {
    Sejour::factory()->create([
        'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-05', 'depart' => '2026-11-08',
        'devis' => ['total_ht' => 90000, 'hebergement_net_ht' => 90000, 'total_tva' => 0], 'net_a_payer' => 90000,
        'prix_proprietaire_par_nuit' => 10000,
    ]);

    connecteAdminControle();

    $reponse = test()->getJson('/api/v1/backoffice/comptabilite/controle-coherence')->assertOk();

    expect($reponse->json('data.nombre_total'))->toBe(0);
});

it('signale un encaissement supérieur au net à payer du séjour', function (): void {
    $sejour = Sejour::factory()->create(['etat' => EtatDuSejour::Confirme, 'net_a_payer' => 50000]);
    $reglement = reglementEffectue([
        'sens' => 'encaissement', 'guichet' => 'sejours', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => User::factory()->create()->id, 'montant' => 70000, 'mode' => 'especes', 'notes' => 'Trop perçu',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => now(),
    ]);
    Imputation::create(['reglement_id' => $reglement->id, 'affaire_type' => 'sejour', 'affaire_id' => $sejour->id, 'montant' => 70000]);

    connecteAdminControle();

    test()->getJson('/api/v1/backoffice/comptabilite/controle-coherence')->assertOk()
        ->assertJsonCount(1, 'data.anomalies.encaisse_superieur_au_facture')
        ->assertJsonPath('data.anomalies.encaisse_superieur_au_facture.0.sejour', $sejour->reference);
});

it('signale un séjour vendu à perte, sous le coût propriétaire', function (): void {
    Sejour::factory()->create([
        'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-05', 'depart' => '2026-11-08', // 3 nuits
        'devis' => ['hebergement_net_ht' => 30000], 'prix_proprietaire_par_nuit' => 15000, // coût = 45000 > 30000 vendu
    ]);

    connecteAdminControle();

    test()->getJson('/api/v1/backoffice/comptabilite/controle-coherence')->assertOk()
        ->assertJsonCount(1, 'data.anomalies.sejours_vendus_a_perte');
});

it('signale une caution retenue sans motif enregistré', function (): void {
    Sejour::factory()->create(['etat' => EtatDuSejour::Cloture, 'caution' => 50000, 'caution_retenue' => 15000, 'caution_retenue_motif' => null]);

    connecteAdminControle();

    test()->getJson('/api/v1/backoffice/comptabilite/controle-coherence')->assertOk()
        ->assertJsonCount(1, 'data.anomalies.cautions_retenues_non_justifiees');
});

it('refuse l’accès à un profil non administrateur', function (): void {
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()));

    test()->getJson('/api/v1/backoffice/comptabilite/controle-coherence')->assertForbidden();
});
