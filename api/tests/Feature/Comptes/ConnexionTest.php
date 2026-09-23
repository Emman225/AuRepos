<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * En production chaque requête démarre un processus neuf. Dans un test, la garde JWT
 * garde en mémoire le dernier jeton et le dernier compte : on la remet à zéro pour
 * que l'appel suivant soit jugé sur son seul en-tête Authorization.
 */
function nouvelleRequete(): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
}

function connecter(string $identifiant, string $motDePasse = 'password')
{
    return test()->postJson('/api/v1/auth/connexion', ['identifiant' => $identifiant, 'mot_de_passe' => $motDePasse]);
}

it('connecte un client par son courriel et lui remet un jeton', function (): void {
    $client = User::factory()->create(['email' => 'awa@exemple.ci']);

    connecter('awa@exemple.ci')
        ->assertOk()
        ->assertJsonPath('data.type', 'Bearer')
        ->assertJsonPath('data.utilisateur.id', $client->id)
        ->assertJsonPath('data.utilisateur.profil', 'client')
        ->assertJsonPath('data.utilisateur.espace', 'client')
        ->assertJsonStructure(['data' => ['jeton', 'expire_dans']]);

    expect($client->refresh()->derniere_connexion_le)->not->toBeNull();
});

it('ne tient pas compte de la casse du courriel', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);

    connecter('  AWA@Exemple.CI ')->assertOk();
});

it('connecte le personnel par son identifiant généré et un partenaire par son téléphone', function (): void {
    User::factory()->profil(Profil::Gestionnaire)->create(['identifiant' => 'GES-0042']);
    User::factory()->profil(Profil::Chauffeur)->create(['telephone' => '0707070707']);

    connecter('GES-0042')->assertOk()->assertJsonPath('data.utilisateur.espace', 'backoffice');
    connecter('0707070707')->assertOk()->assertJsonPath('data.utilisateur.espace', 'chauffeur');
});

it('refuse un mauvais mot de passe sans dire si le compte existe', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);

    $mauvaisMotDePasse = connecter('awa@exemple.ci', 'faux')->assertUnauthorized()->json('message');
    $compteInconnu = connecter('inconnu@exemple.ci', 'faux')->assertUnauthorized()->json('message');

    expect($mauvaisMotDePasse)->toBe($compteInconnu)->toBe('Identifiant ou mot de passe incorrect.');
});

it('refuse un compte bloqué ou en attente, même avec le bon mot de passe', function (): void {
    User::factory()->bloque()->create(['email' => 'bloque@exemple.ci']);
    User::factory()->enAttente()->create(['email' => 'attente@exemple.ci']);

    connecter('bloque@exemple.ci')->assertUnauthorized();
    // En attente de vérification : refusé aussi, mais avec de quoi guider le client (voir InscriptionTest).
    connecter('attente@exemple.ci')->assertForbidden();
});

it('exige l’identifiant et le mot de passe', function (): void {
    test()->postJson('/api/v1/auth/connexion', [])
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['identifiant', 'mot_de_passe']]);
});

it('ne renvoie jamais le mot de passe, même haché', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);

    expect(connecter('awa@exemple.ci')->getContent())->not->toContain('password')->not->toContain('$2y$');
});

it('rend le compte connecté avec son agence et son droit d’encaisser', function (): void {
    $agence = Agence::factory()->create(['nom' => 'Agence Cocody']);
    $admin = User::factory()->profil(Profil::Administrateur)->create(['agence_id' => $agence->id]);
    $jeton = auth('api')->login($admin);

    test()->withToken($jeton)->getJson('/api/v1/auth/moi')
        ->assertOk()
        ->assertJsonPath('data.agence.nom', 'Agence Cocody')
        ->assertJsonPath('data.peut_encaisser', true);
});

it('interdit d’encaisser à un administrateur sans agence', function (): void {
    $admin = User::factory()->profil(Profil::Administrateur)->create();

    test()->withToken(auth('api')->login($admin))->getJson('/api/v1/auth/moi')
        ->assertJsonPath('data.peut_encaisser', false);
});

it('répond 401 sans jeton ou avec un jeton falsifié', function (): void {
    test()->getJson('/api/v1/auth/moi')->assertUnauthorized();
    test()->withToken('jeton.completement.faux')->getJson('/api/v1/auth/moi')->assertUnauthorized();
});

it('révoque le jeton à la déconnexion', function (): void {
    $jeton = auth('api')->login(User::factory()->create());

    test()->withToken($jeton)->postJson('/api/v1/auth/deconnexion')->assertOk();

    nouvelleRequete();
    test()->withToken($jeton)->getJson('/api/v1/auth/moi')->assertUnauthorized();
});

it('prolonge la session et révoque l’ancien jeton', function (): void {
    $ancien = auth('api')->login(User::factory()->create());

    $nouveau = test()->withToken($ancien)->postJson('/api/v1/auth/rafraichir')
        ->assertOk()
        ->json('data.jeton');

    expect($nouveau)->not->toBe($ancien);

    nouvelleRequete();
    test()->withToken($nouveau)->getJson('/api/v1/auth/moi')->assertOk();

    nouvelleRequete();
    test()->withToken($ancien)->getJson('/api/v1/auth/moi')->assertUnauthorized();
});
