<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->proprietaire = Proprietaire::factory()->create();
    $this->residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id, 'nom' => 'Résidence Awa']);
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);

    $this->autreProprietaire = Proprietaire::factory()->create();
    $this->autreResidence = Residence::factory()->create(['proprietaire_id' => $this->autreProprietaire->id, 'nom' => 'Résidence Koné']);
    $this->autreLogement = Logement::factory()->create(['residence_id' => $this->autreResidence->id]);
});

/** Connecte LE propriétaire du jeu de données de ce test — jamais la valeur par défaut d'un beforeEach. */
function seConnecterCommeLeProprietaire(): void
{
    test()->withToken(auth('api')->login(test()->proprietaire->utilisateur));
}

it('refuse l’accès sans authentification', function (): void {
    test()->getJson('/api/v1/proprietaire/tableau-de-bord')->assertUnauthorized();
});

it('renvoie une 404 propre quand le compte connecté n’a pas de fiche propriétaire', function (): void {
    $sansFiche = User::factory()->profil(Profil::Proprietaire)->create();
    test()->withToken(auth('api')->login($sansFiche));

    test()->getJson('/api/v1/proprietaire/tableau-de-bord')->assertNotFound();
});

it('un autre profil (client) ne peut pas accéder à l’espace propriétaire', function (): void {
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Client)->create()));

    test()->getJson('/api/v1/proprietaire/tableau-de-bord')->assertForbidden();
    test()->getJson('/api/v1/proprietaire/residences')->assertForbidden();
});

// ---------------------------------------------------------------- résidences

it('liste SES résidences, jamais celles d’un autre propriétaire', function (): void {
    seConnecterCommeLeProprietaire();
    $reponse = test()->getJson('/api/v1/proprietaire/residences')->assertOk();

    expect(array_column($reponse->json('data'), 'id'))->toBe([$this->residence->id]);
    $reponse->assertJsonPath('data.0.nom', 'Résidence Awa')
        ->assertJsonPath('data.0.nombre_logements', 1)
        ->assertJsonPath('data.0.disponibilite', Disponibilite::Disponible->value);
});

it('atteint les logements de sa résidence, mais jamais ceux d’une autre (404)', function (): void {
    seConnecterCommeLeProprietaire();
    test()->getJson("/api/v1/proprietaire/residences/{$this->residence->id}/logements")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    test()->getJson("/api/v1/proprietaire/residences/{$this->autreResidence->id}/logements")
        ->assertNotFound();
});

it('atteint les séjours de son logement, mais jamais ceux d’un logement d’un autre propriétaire (404)', function (): void {
    seConnecterCommeLeProprietaire();
    $sejour = Sejour::factory()->create([
        'logement_id' => $this->logement->id,
        'etat' => EtatDuSejour::Arrive,
        'client_id' => User::factory()->create(['nom' => 'Bamba', 'prenoms' => 'Issa'])->id,
    ]);

    $reponse = test()->getJson("/api/v1/proprietaire/logements/{$this->logement->id}/sejours")->assertOk();

    expect(array_column($reponse->json('data'), 'reference'))->toBe([$sejour->reference]);
    $reponse->assertJsonPath('data.0.client', 'Issa Bamba');
    expect($reponse->json('data.0'))->not->toHaveKeys(['code_d_arrivee', 'telephone']);

    test()->getJson("/api/v1/proprietaire/logements/{$this->autreLogement->id}/sejours")->assertNotFound();
});

// ---------------------------------------------------------------- tableau de bord

it('renvoie des compteurs réels sur un jeu de données construit dans le test', function (): void {
    seConnecterCommeLeProprietaire();
    $autreLogementAMoi = Logement::factory()->create(['residence_id' => $this->residence->id]);
    $client = fn () => User::factory()->create()->id;
    $aujourdHui = Carbon::today();

    // Un séjour en cours (arrivé) sur mon premier logement.
    Sejour::factory()->create(['logement_id' => $this->logement->id, 'etat' => EtatDuSejour::Arrive, 'client_id' => $client()]);
    // Une arrivée confirmée dans 3 jours sur mon second logement : comptée.
    Sejour::factory()->create([
        'logement_id' => $autreLogementAMoi->id, 'etat' => EtatDuSejour::Confirme, 'client_id' => $client(),
        'arrivee' => $aujourdHui->copy()->addDays(3)->toDateString(), 'depart' => $aujourdHui->copy()->addDays(6)->toDateString(),
    ]);
    // Une arrivée confirmée dans 15 jours : hors fenêtre des 7 jours, non comptée.
    Sejour::factory()->create([
        'logement_id' => $this->logement->id, 'etat' => EtatDuSejour::Confirme, 'client_id' => $client(),
        'arrivee' => $aujourdHui->copy()->addDays(15)->toDateString(), 'depart' => $aujourdHui->copy()->addDays(18)->toDateString(),
    ]);
    // Un séjour sur la résidence d'un AUTRE propriétaire : jamais compté.
    Sejour::factory()->create(['logement_id' => $this->autreLogement->id, 'etat' => EtatDuSejour::Arrive, 'client_id' => $client()]);

    test()->getJson('/api/v1/proprietaire/tableau-de-bord')
        ->assertOk()
        ->assertJsonPath('data.nombre_residences', 1)
        ->assertJsonPath('data.nombre_logements', 2)
        ->assertJsonPath('data.sejours_en_cours', 1)
        ->assertJsonPath('data.arrivees_sous_7_jours', 1);
});
