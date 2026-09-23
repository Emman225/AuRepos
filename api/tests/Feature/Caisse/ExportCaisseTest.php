<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $agence = Agence::factory()->create();
    $this->caissier = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => $agence->id]);
    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000, 'acompte_exige' => 30000]);

    app(Caisse::class)->saisirUnEncaissement(
        $this->caissier, $this->client, [$this->sejour->id], 30000, ModeDeReglement::Especes, 'Versement au guichet',
    );

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $this->withToken(auth('api')->login($this->caissier));
});

it('exporte la liste de la caisse en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    $reponse = $this->getJson('/api/v1/backoffice/caisse/reglements/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
    expect($reponse->getContent())->not->toBeEmpty();
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('refuse un format d’export inconnu', function (): void {
    $this->getJson('/api/v1/backoffice/caisse/reglements/export?format=csv')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

it('applique le filtre de période à l’export, comme à la liste', function (): void {
    // Hors fenêtre : le règlement (saisi aujourd'hui) ne doit apparaître dans aucune ligne.
    $horsFenetre = $this->getJson('/api/v1/backoffice/caisse/reglements/export?format=xlsx&du='.now()->addDays(2)->format('Y-m-d').'&au='.now()->addDays(3)->format('Y-m-d'));
    $horsFenetre->assertOk();
    expect(nombreDeLignesDuClasseur((string) $horsFenetre->getContent()))->toBe(0);

    // Dans la fenêtre : le règlement doit apparaître.
    $dansLaFenetre = $this->getJson('/api/v1/backoffice/caisse/reglements/export?format=xlsx&du='.now()->subDay()->format('Y-m-d').'&au='.now()->addDay()->format('Y-m-d'));
    $dansLaFenetre->assertOk();
    expect(nombreDeLignesDuClasseur((string) $dansLaFenetre->getContent()))->toBe(1);
});

/** Lit le classeur reçu en réponse et compte ses lignes de données (hors en-tête). */
function nombreDeLignesDuClasseur(string $contenu): int
{
    $chemin = tempnam(sys_get_temp_dir(), 'test-export-');
    file_put_contents($chemin, $contenu);

    try {
        $feuille = IOFactory::load($chemin)->getActiveSheet();

        return max($feuille->getHighestRow() - 1, 0);
    } finally {
        unlink($chemin);
    }
}

it('route /reglements/export avant /reglements/{reglement} : "export" n’est jamais pris pour un identifiant', function (): void {
    $this->getJson('/api/v1/backoffice/caisse/reglements/export?format=xlsx')->assertOk();
});
