<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->logement = Logement::factory()->create();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
});

it('filtre les réservations par mode de règlement (file « client à terme », CdC § 6.1)', function (): void {
    $aTerme = Sejour::factory()->create(['logement_id' => $this->logement->id, 'mode_reglement' => 'a_terme']);
    Sejour::factory()->create(['logement_id' => $this->logement->id, 'mode_reglement' => 'agence']);

    $reponse = test()->getJson('/api/v1/backoffice/sejours?mode_reglement=a_terme')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'reference'))->toBe([$aTerme->reference]);
});

it('filtre la période sur le DÉPART plutôt que l’arrivée quand champ_date=depart (file « Départs du jour »)', function (): void {
    $departAujourdhui = Sejour::factory()->create([
        'logement_id' => $this->logement->id, 'arrivee' => '2026-11-01', 'depart' => '2026-11-10',
    ]);
    // Arrive le même jour que le premier, mais part plus tard : ne doit PAS apparaître sur « départs du jour ».
    Sejour::factory()->create([
        'logement_id' => $this->logement->id, 'arrivee' => '2026-11-01', 'depart' => '2026-11-15',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/sejours?champ_date=depart&du=2026-11-10&au=2026-11-10')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'reference'))->toBe([$departAujourdhui->reference]);
});

it('exporte la liste des réservations en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    Sejour::factory()->create(['logement_id' => $this->logement->id]);

    $reponse = test()->getJson('/api/v1/backoffice/sejours/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /sejours/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    test()->getJson('/api/v1/backoffice/sejours/export?format=xlsx')->assertOk();
});
