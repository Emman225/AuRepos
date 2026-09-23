<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Models\ContratRecurrent;
use App\Domain\Maintenance\Services\ContratsRecurrents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');

    $this->admin = User::factory()->profil(Profil::Administrateur)->create();

    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
});

function connecteInventaire(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('ajoute, modifie et supprime un article d’inventaire', function (): void {
    connecteInventaire($this->admin);
    $base = "/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/inventaire";

    $reponse = test()->postJson($base, ['nom' => 'Téléviseur', 'quantite' => 1, 'valeur_remplacement' => 150000])->assertCreated();
    $articleId = $reponse->json('data.id');

    test()->putJson("{$base}/{$articleId}", ['quantite' => 2])->assertOk()->assertJsonPath('data.quantite', 2);
    test()->deleteJson("{$base}/{$articleId}")->assertOk();

    expect(test()->getJson($base)->json('data'))->toHaveCount(0);
});

it('refuse une quantité négative', function (): void {
    connecteInventaire($this->admin);

    test()->postJson("/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/inventaire", [
        'nom' => 'Chaise', 'quantite' => -1,
    ])->assertStatus(422);
});

it('crée un contrat récurrent et reprogramme son prochain rappel selon la périodicité', function (): void {
    connecteInventaire($this->admin);
    $base = "/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/contrats-recurrents";

    $reponse = test()->postJson($base, [
        'nom' => 'Entretien climatisation', 'periodicite' => 'trimestrielle', 'prochain_rappel' => '2026-12-01',
    ])->assertCreated();

    expect($reponse->json('data.periodicite'))->toBe('trimestrielle')
        ->and($reponse->json('data.prochain_rappel'))->toBe('2026-12-01');

    $contrat = ContratRecurrent::first();
    $contrat = app(ContratsRecurrents::class)->reprogrammerLeProchainRappel($contrat);
    expect($contrat->prochain_rappel->toDateString())->toBe('2027-03-01');
});

it('désactive un contrat récurrent', function (): void {
    connecteInventaire($this->admin);
    $base = "/api/v1/backoffice/residences/{$this->residence->id}/logements/{$this->logement->id}/contrats-recurrents";

    $reponse = test()->postJson($base, [
        'nom' => 'Entretien piscine', 'periodicite' => 'mensuelle', 'prochain_rappel' => '2026-12-01',
    ])->assertCreated();
    $contratId = $reponse->json('data.id');

    test()->putJson("{$base}/{$contratId}/desactivation")->assertOk();

    expect(ContratRecurrent::find($contratId)->actif)->toBeFalse();
});

it('les rappels dus interrogent uniquement les contrats actifs dont l’échéance est passée', function (): void {
    ContratRecurrent::create([
        'logement_id' => $this->logement->id, 'nom' => 'Contrat actif dû', 'periodicite' => 'mensuelle',
        'prochain_rappel' => '2026-11-09', 'cree_par' => $this->admin->id,
    ]);
    ContratRecurrent::create([
        'logement_id' => $this->logement->id, 'nom' => 'Contrat futur', 'periodicite' => 'mensuelle',
        'prochain_rappel' => '2026-12-09', 'cree_par' => $this->admin->id,
    ]);

    $dus = app(ContratsRecurrents::class)->rappelsDus(Carbon::today());
    expect($dus)->toHaveCount(1)->and($dus->first()->nom)->toBe('Contrat actif dû');
});
