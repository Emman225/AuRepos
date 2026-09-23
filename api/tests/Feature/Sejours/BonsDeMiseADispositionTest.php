<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\BonsDeMiseADisposition;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterLeProprietaire(Proprietaire $p): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($p->utilisateur));
}

/** Un bon EN ATTENTE (mandat sans validation automatique), prêt à être validé (P3-PRO-01). */
function unBonEnAttente(Proprietaire $proprietaire): BonDeMiseADisposition
{
    $logement = Logement::factory()->create(['residence_id' => Residence::factory()->create(['proprietaire_id' => $proprietaire->id])]);
    $sejour = Sejour::factory()->create(['logement_id' => $logement->id, 'client_id' => User::factory()->create()->id]);

    return BonDeMiseADisposition::create([
        'sejour_id' => $sejour->id, 'proprietaire_id' => $proprietaire->id, 'nuitees' => $sejour->nombreDeNuits(),
        'prix_proprietaire_par_nuit' => 15000, 'etat' => 'en_attente', 'valide_automatiquement' => false,
    ]);
}

it('laisse le propriétaire valider SON bon en attente, journalise, et refuse un second traitement', function (): void {
    $proprietaire = Proprietaire::factory()->create();
    $bon = unBonEnAttente($proprietaire);
    connecterLeProprietaire($proprietaire);

    test()->getJson('/api/v1/proprietaire/bons')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.etat', 'en_attente');

    test()->putJson("/api/v1/proprietaire/bons/{$bon->id}/validation")->assertOk()->assertJsonPath('data.etat', 'valide');

    expect($bon->refresh()->etat)->toBe('valide')->and($bon->valide_par)->toBe($proprietaire->user_id);
    expect(EntreeAudit::where('action', 'bon_valide')->sole()->user_id)->toBe($proprietaire->user_id);

    test()->putJson("/api/v1/proprietaire/bons/{$bon->id}/validation")->assertStatus(409);
});

it('empêche un propriétaire de valider le bon d’un autre (404)', function (): void {
    $autre = Proprietaire::factory()->create();
    $bon = unBonEnAttente($autre);
    connecterLeProprietaire(Proprietaire::factory()->create());

    test()->putJson("/api/v1/proprietaire/bons/{$bon->id}/validation")->assertNotFound();
});

it('refuse de valider deux fois via le service directement', function (): void {
    $proprietaire = Proprietaire::factory()->create();
    $bon = unBonEnAttente($proprietaire);

    $service = app(BonsDeMiseADisposition::class);
    $service->valider($bon, $proprietaire->utilisateur);

    expect(fn () => $service->valider($bon->refresh(), $proprietaire->utilisateur))->toThrow(ErreurMetier::class);
});
