<?php

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\ComptesDuPersonnel;
use App\Mail\BienvenuePersonnelMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    $this->agence = Agence::factory()->create();
});

function connecterUnPersonnel(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create(['agence_id' => Agence::factory()->create()->id]);
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- liste

it('filtre le personnel par recherche (nom, courriel, identifiant) et pagine', function (): void {
    connecterUnPersonnel(Profil::Administrateur);
    User::factory()->profil(Profil::Gouvernante)->create(['nom' => 'Kouassi', 'email' => 'kouassi@residences.test']);
    User::factory()->profil(Profil::Gouvernante)->create(['nom' => 'Bamba', 'email' => 'bamba@residences.test']);

    test()->getJson('/api/v1/backoffice/personnel?recherche=Kouassi')->assertOk()
        ->assertJsonCount(1, 'data.elements')
        ->assertJsonPath('data.elements.0.nom', 'Kouassi');

    test()->getJson('/api/v1/backoffice/personnel?par_page=5&page=1')->assertOk()
        ->assertJsonPath('data.pagination.par_page', 5);
});

it('exporte la liste du personnel en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterUnPersonnel(Profil::Administrateur);

    $reponse = test()->getJson('/api/v1/backoffice/personnel/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /personnel/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterUnPersonnel(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/personnel/export?format=xlsx')->assertOk();
});

// ---------------------------------------------------------------- création

it('crée un compte gestionnaire avec un identifiant généré, et envoie le courriel de bienvenue', function (): void {
    connecterUnPersonnel(Profil::Administrateur);

    $reponse = test()->postJson('/api/v1/backoffice/personnel', [
        'nom' => 'Koné', 'prenoms' => 'Awa', 'email' => 'awa.kone@residences.test', 'telephone' => '+2250700000001',
        'profil' => 'gestionnaire', 'agence_id' => $this->agence->id,
    ])->assertCreated();

    $identifiant = $reponse->json('data.identifiant');
    expect($identifiant)->not->toBeNull()->and($identifiant)->toBe('akone');

    $utilisateur = User::where('email', 'awa.kone@residences.test')->firstOrFail();
    expect($utilisateur->profil)->toBe(Profil::Gestionnaire)
        ->and($utilisateur->agence_id)->toBe($this->agence->id)
        ->and($utilisateur->email_verified_at)->not->toBeNull();

    Mail::assertQueued(BienvenuePersonnelMail::class, fn ($m) => $m->hasTo('awa.kone@residences.test') && $m->utilisateur->identifiant === 'akone');
});

it('départage deux identifiants qui se ressembleraient par un suffixe numérique', function (): void {
    connecterUnPersonnel(Profil::Administrateur);

    test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'Koné', 'prenoms' => 'Awa', 'email' => 'awa1@residences.test', 'profil' => 'gouvernante'])->assertCreated();
    $second = test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'Koné', 'prenoms' => 'Aya', 'email' => 'aya2@residences.test', 'profil' => 'gouvernante']);

    expect($second->json('data.identifiant'))->toBe('akone2');
});

it('rattache un gestionnaire à ses résidences, dès la création', function (): void {
    connecterUnPersonnel(Profil::Administrateur);
    [$r1, $r2, $autre] = [Residence::factory()->create(), Residence::factory()->create(), Residence::factory()->create()];

    $reponse = test()->postJson('/api/v1/backoffice/personnel', [
        'nom' => 'Traoré', 'email' => 'traore@residences.test', 'profil' => 'gestionnaire', 'agence_id' => $this->agence->id,
        'residences' => [$r1->id, $r2->id],
    ])->assertCreated();

    $utilisateur = User::where('email', 'traore@residences.test')->firstOrFail();
    expect($utilisateur->residences->pluck('id')->sort()->values()->all())->toBe([$r1->id, $r2->id])
        ->and($utilisateur->residences->pluck('id'))->not->toContain($autre->id);
});

it('exige une agence pour un administrateur ou un gestionnaire, pas pour les autres profils du personnel', function (): void {
    connecterUnPersonnel(Profil::SuperAdministrateur);

    test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'Sans Agence', 'email' => 'sa@residences.test', 'profil' => 'gestionnaire'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'agence_obligatoire');

    test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'Gouvernante', 'email' => 'g@residences.test', 'profil' => 'gouvernante'])
        ->assertCreated();
});

it('refuse qu’un simple administrateur crée un compte administrateur ou super administrateur', function (): void {
    connecterUnPersonnel(Profil::Administrateur);

    expect(fn () => app(ComptesDuPersonnel::class)->creer(
        User::where('profil', Profil::Administrateur)->firstOrFail(),
        ['nom' => 'Nouvel Admin', 'email' => 'na@residences.test', 'profil' => 'administrateur', 'agence_id' => $this->agence->id],
    ))->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('privilege_insuffisant'));
});

it('laisse un super administrateur créer un autre administrateur', function (): void {
    connecterUnPersonnel(Profil::SuperAdministrateur);

    test()->postJson('/api/v1/backoffice/personnel', [
        'nom' => 'Nouvel Admin', 'email' => 'na@residences.test', 'profil' => 'administrateur', 'agence_id' => $this->agence->id,
    ])->assertCreated();
});

it('refuse un profil qui n’est pas du personnel (client, propriétaire…)', function (): void {
    connecterUnPersonnel(Profil::Administrateur);

    test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'X', 'email' => 'x@residences.test', 'profil' => 'client'])
        ->assertStatus(422)->assertJsonValidationErrors('profil');
});

it('ferme la création de personnel aux gestionnaires', function (): void {
    connecterUnPersonnel(Profil::Gestionnaire);

    test()->postJson('/api/v1/backoffice/personnel', ['nom' => 'X', 'email' => 'x@residences.test', 'profil' => 'gouvernante'])
        ->assertForbidden();
});

// ---------------------------------------------------------------- rattachement ultérieur

it('met à jour les résidences d’un gestionnaire déjà créé', function (): void {
    connecterUnPersonnel(Profil::Administrateur);
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => $this->agence->id]);
    $residence = Residence::factory()->create();

    test()->putJson("/api/v1/backoffice/personnel/{$gestionnaire->id}/residences", ['residences' => [$residence->id]])
        ->assertOk()->assertJsonCount(1, 'data.residences');

    expect($gestionnaire->refresh()->residences->pluck('id')->all())->toBe([$residence->id]);
});

it('refuse de rattacher des résidences à qui n’est pas gestionnaire', function (): void {
    connecterUnPersonnel(Profil::Administrateur);
    $gouvernante = User::factory()->profil(Profil::Gouvernante)->create();
    $residence = Residence::factory()->create();

    expect(fn () => app(ComptesDuPersonnel::class)->rattacherAuxResidences($gouvernante, [$residence->id]))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('profil_sans_residences'));
});
