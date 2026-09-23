<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Enums\UsageDuCode;
use App\Domain\Comptes\Models\CodeVerification;
use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\CodesDeVerification;
use App\Mail\CodeDeVerificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

function saisieInscription(array $surcharge = []): array
{
    return [
        'nom' => 'Koné', 'prenoms' => 'Awa', 'email' => 'awa@exemple.ci', 'telephone' => '07 07 07 07 07',
        'mot_de_passe' => 'Abidjan2026', 'mot_de_passe_confirmation' => 'Abidjan2026',
        'conditions_acceptees' => true, ...$surcharge,
    ];
}

/** Le code en clair n'existe que dans le courriel : c'est là qu'on le lit, comme le client. */
function codeEnvoyeA(string $email): string
{
    $code = null;
    Mail::assertQueued(CodeDeVerificationMail::class, function (CodeDeVerificationMail $m) use ($email, &$code) {
        if ($m->hasTo($email)) {
            $code = $m->code;
        }

        return true;
    });

    return (string) $code;
}

it('crée un compte client en attente et lui envoie un code', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription())
        ->assertCreated()
        ->assertJsonPath('data.email', 'awa@exemple.ci');

    $compte = User::firstWhere('email', 'awa@exemple.ci');
    expect($compte->profil)->toBe(Profil::Client)
        ->and($compte->statut)->toBe(StatutCompte::EnAttente)
        ->and($compte->telephone)->toBe('0707070707');

    Mail::assertQueued(CodeDeVerificationMail::class, fn ($m) => $m->hasTo('awa@exemple.ci'));
});

it('ne laisse personne s’inscrire avec un autre profil que client', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription(['profil' => 'administrateur', 'statut' => 'actif']))
        ->assertCreated();

    $compte = User::firstWhere('email', 'awa@exemple.ci');
    expect($compte->profil)->toBe(Profil::Client)->and($compte->statut)->toBe(StatutCompte::EnAttente);
});

it('ne garde jamais le code en clair', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());

    $code = codeEnvoyeA('awa@exemple.ci');
    expect($code)->toMatch('/^\d{6}$/')
        ->and(CodeVerification::first()->code_hash)->not->toContain($code);
});

it('refuse les saisies incorrectes, en français', function (array $surcharge, string $champ): void {
    User::factory()->create(['email' => 'pris@exemple.ci']);

    $reponse = test()->postJson('/api/v1/auth/inscription', saisieInscription($surcharge))->assertStatus(422);

    expect($reponse->json("errors.{$champ}.0"))->toBeString()->not->toContain('validation.');
})->with([
    'courriel déjà pris' => [['email' => 'PRIS@exemple.ci'], 'email'],
    'courriel mal formé' => [['email' => 'pas-un-courriel'], 'email'],
    'mot de passe trop court' => [['mot_de_passe' => 'Ab1', 'mot_de_passe_confirmation' => 'Ab1'], 'mot_de_passe'],
    'mot de passe sans chiffre' => [['mot_de_passe' => 'abcdefghij', 'mot_de_passe_confirmation' => 'abcdefghij'], 'mot_de_passe'],
    'confirmation différente' => [['mot_de_passe_confirmation' => 'Autre2026'], 'mot_de_passe'],
    'conditions non acceptées' => [['conditions_acceptees' => false], 'conditions_acceptees'],
    'téléphone fantaisiste' => [['telephone' => 'abc'], 'telephone'],
    'nom manquant' => [['nom' => ''], 'nom'],
]);

it('oriente vers la vérification un client qui se connecte avant d’avoir saisi son code', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());

    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'Abidjan2026'])
        ->assertForbidden()
        ->assertJsonPath('errors.code.0', 'courriel_non_verifie');

    // Avec un mauvais mot de passe, on ne dit rien de l'état du compte.
    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'faux'])
        ->assertUnauthorized();
});

it('active le compte et ouvre la session avec le bon code', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());

    test()->postJson('/api/v1/auth/verification', ['email' => 'awa@exemple.ci', 'code' => codeEnvoyeA('awa@exemple.ci')])
        ->assertOk()
        ->assertJsonPath('data.utilisateur.espace', 'client')
        ->assertJsonStructure(['data' => ['jeton']]);

    $compte = User::firstWhere('email', 'awa@exemple.ci');
    expect($compte->statut)->toBe(StatutCompte::Actif)->and($compte->email_verified_at)->not->toBeNull();
});

it('n’accepte un code qu’une seule fois', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());
    $code = codeEnvoyeA('awa@exemple.ci');

    test()->postJson('/api/v1/auth/verification', ['email' => 'awa@exemple.ci', 'code' => $code])->assertOk();
    test()->postJson('/api/v1/auth/verification', ['email' => 'awa@exemple.ci', 'code' => $code])->assertStatus(422);
});

it('refuse un code expiré', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());
    $code = codeEnvoyeA('awa@exemple.ci');

    test()->travel(CodesDeVerification::DUREE_EN_MINUTES + 1)->minutes();

    test()->postJson('/api/v1/auth/verification', ['email' => 'awa@exemple.ci', 'code' => $code])->assertStatus(422);
});

it('verrouille le code après cinq essais faux, même si le sixième est le bon', function (): void {
    $compte = User::factory()->enAttente()->create(['email' => 'awa@exemple.ci']);
    $codes = app(CodesDeVerification::class);
    $codes->envoyer($compte, UsageDuCode::VerificationCourriel);
    $bon = codeEnvoyeA('awa@exemple.ci');
    $faux = $bon === '000000' ? '111111' : '000000';

    foreach (range(1, CodesDeVerification::ESSAIS_MAXIMUM) as $essai) {
        expect(fn () => $codes->consommer($compte, UsageDuCode::VerificationCourriel, $faux))
            ->toThrow(ValidationException::class);
    }

    expect(fn () => $codes->consommer($compte, UsageDuCode::VerificationCourriel, $bon))
        ->toThrow(ValidationException::class);
});

it('donne la même réponse à un code faux et à un compte inconnu', function (): void {
    test()->postJson('/api/v1/auth/inscription', saisieInscription());

    $codeFaux = test()->postJson('/api/v1/auth/verification', ['email' => 'awa@exemple.ci', 'code' => '000000']);
    $inconnu = test()->postJson('/api/v1/auth/verification', ['email' => 'personne@exemple.ci', 'code' => '000000']);

    expect($codeFaux->status())->toBe(422)
        ->and($inconnu->status())->toBe(422)
        ->and($codeFaux->json('errors'))->toBe($inconnu->json('errors'));
});

it('un nouveau code annule le précédent', function (): void {
    $compte = User::factory()->enAttente()->create(['email' => 'awa@exemple.ci']);
    $codes = app(CodesDeVerification::class);

    $codes->envoyer($compte, UsageDuCode::VerificationCourriel);
    $codes->envoyer($compte, UsageDuCode::VerificationCourriel);

    expect(CodeVerification::where('user_id', $compte->id)->count())->toBe(1);
});

it('crée le compte même si l’envoi du courriel échoue', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('serveur de messagerie en panne'));

    test()->postJson('/api/v1/auth/inscription', saisieInscription())->assertCreated();

    expect(User::where('email', 'awa@exemple.ci')->exists())->toBeTrue();
});
