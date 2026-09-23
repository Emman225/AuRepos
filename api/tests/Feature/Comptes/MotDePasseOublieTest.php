<?php

use App\Domain\Comptes\Models\User;
use App\Mail\CodeDeVerificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

function codeDeReinitialisation(): string
{
    $code = null;
    Mail::assertQueued(CodeDeVerificationMail::class, function (CodeDeVerificationMail $m) use (&$code) {
        $code = $m->code;

        return true;
    });

    return (string) $code;
}

function reinitialiser(string $code, string $motDePasse = 'Nouveau2026')
{
    return test()->postJson('/api/v1/auth/mot-de-passe/reinitialiser', [
        'email' => 'awa@exemple.ci', 'code' => $code,
        'mot_de_passe' => $motDePasse, 'mot_de_passe_confirmation' => $motDePasse,
    ]);
}

it('répond la même chose que le compte existe ou non, mais n’écrit qu’au vrai compte', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);

    $existe = test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'awa@exemple.ci'])->assertOk();
    $inconnu = test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'personne@exemple.ci'])->assertOk();

    expect($existe->json('message'))->toBe($inconnu->json('message'));
    Mail::assertQueuedCount(1);
});

it('n’envoie rien à un compte bloqué', function (): void {
    User::factory()->bloque()->create(['email' => 'awa@exemple.ci']);

    test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'awa@exemple.ci'])->assertOk();

    Mail::assertNothingQueued();
});

it('change le mot de passe avec le bon code', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);
    test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'awa@exemple.ci']);

    reinitialiser(codeDeReinitialisation())->assertOk();

    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'Nouveau2026'])->assertOk();
    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'password'])->assertUnauthorized();
});

it('refuse un mauvais code et un mot de passe trop faible', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);
    test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'awa@exemple.ci']);
    $code = codeDeReinitialisation();

    reinitialiser($code === '000000' ? '111111' : '000000')->assertStatus(422)->assertJsonStructure(['errors' => ['code']]);
    reinitialiser($code, 'faible')->assertStatus(422)->assertJsonStructure(['errors' => ['mot_de_passe']]);
});

it('fait tomber les sessions ouvertes avant le changement de mot de passe', function (): void {
    $compte = User::factory()->create(['email' => 'awa@exemple.ci']);
    $jetonVole = auth('api')->login($compte);

    test()->travel(5)->seconds();
    test()->postJson('/api/v1/auth/mot-de-passe/oublie', ['email' => 'awa@exemple.ci']);
    reinitialiser(codeDeReinitialisation())->assertOk();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken($jetonVole)->getJson('/api/v1/auth/moi')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Votre mot de passe a changé. Reconnectez-vous.');
});

it('coupe /auth/moi à un compte bloqué sans attendre la fin de son jeton', function (): void {
    $compte = User::factory()->create();
    $jeton = auth('api')->login($compte);

    $compte->update(['statut' => 'bloque']);
    auth('api')->forgetUser();
    auth('api')->unsetToken();

    test()->withToken($jeton)->getJson('/api/v1/auth/moi')->assertUnauthorized();
});
