<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->logement = Logement::factory()->create();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
});

it('liste, dans la période demandée, un séjour confirmé avec son logement et le nom du client', function (): void {
    $client = User::factory()->create(['nom' => 'Kouassi', 'prenoms' => 'Jean']);
    $sejour = Sejour::factory()->create([
        'logement_id' => $this->logement->id,
        'client_id' => $client->id,
        'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-09-20',
        'depart' => '2026-09-24',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect(array_column($reponse->json('data.logements'), 'id'))->toContain($this->logement->id);

    $sejours = $reponse->json('data.sejours');
    expect(array_column($sejours, 'reference'))->toBe([$sejour->reference]);
    expect($sejours[0])
        ->logement_id->toBe($this->logement->id)
        ->etat->toBe('confirme')
        ->client_nom->toBe('Jean Kouassi')
        ->arrivee->toBe('2026-09-20')
        ->depart->toBe('2026-09-24');
});

it('n’affiche pas un séjour annulé : il a libéré ses dates (EtatDuSejour::occupeLeCalendrier)', function (): void {
    Sejour::factory()->create([
        'logement_id' => $this->logement->id,
        'etat' => EtatDuSejour::Annule,
        'arrivee' => '2026-09-20',
        'depart' => '2026-09-24',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.sejours'))->toBe([]);
});

it('n’affiche pas un séjour hors de la période demandée', function (): void {
    Sejour::factory()->create([
        'logement_id' => $this->logement->id,
        'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-01-05',
        'depart' => '2026-01-10',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.sejours'))->toBe([]);
});

it('refuse l’accès à un profil hors exploitation', function (): void {
    test()->withToken(auth('api')->login(User::factory()->create()));

    test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertForbidden();
});
