<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PourcentageEntreprise;
use App\Domain\Catalogue\Services\PrixDeLogement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function connecteComme(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- taux global

it('refuse de changer le pourcentage entreprise par le formulaire ordinaire des Paramètres', function (): void {
    $admin = User::factory()->profil(Profil::SuperAdministrateur)->create();

    expect(fn () => app(Parametres::class)->enregistrer('proprietaires', ['pourcentage_entreprise' => 25], $admin))
        ->toThrow(ValidationException::class);
});

it('propose le taux global : il n’entre en vigueur qu’après validation par un second administrateur', function (): void {
    $a1 = connecteComme(Profil::Administrateur);
    $a2 = User::factory()->profil(Profil::Administrateur)->create();

    expect(app(Parametres::class)->valeur('proprietaires.pourcentage_entreprise'))->toBe(20.0);

    $reponse = test()->postJson('/api/v1/backoffice/tarification/pourcentage-entreprise', ['taux' => 25, 'motif' => 'Révision annuelle'])
        ->assertCreated();

    // Toujours l'ancien taux tant que personne d'autre n'a validé.
    expect(app(Parametres::class)->valeur('proprietaires.pourcentage_entreprise'))->toBe(20.0);

    $id = $reponse->json('data.id');
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($a2));
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    expect(app(Parametres::class)->valeur('proprietaires.pourcentage_entreprise'))->toBe(25.0);
});

it('refuse qu’un taux hors limites soit proposé', function (): void {
    connecteComme(Profil::Administrateur);

    test()->postJson('/api/v1/backoffice/tarification/pourcentage-entreprise', ['taux' => 600])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['taux']]);
});

// ---------------------------------------------------------------- dérogation par logement

it('propose une dérogation sur un logement : le taux global reste inchangé pour les autres', function (): void {
    $a1 = connecteComme(Profil::Administrateur);
    $a2 = User::factory()->profil(Profil::Administrateur)->create();
    $logement = Logement::factory()->create();
    $autre = Logement::factory()->create();

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/pourcentage-entreprise", [
        'taux' => 35, 'motif' => 'Logement premium',
    ])->assertCreated();

    expect($logement->refresh()->pourcentage_entreprise_derogation)->toBeNull(); // pas encore validée

    $id = $reponse->json('data.derogation_en_attente.id');
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($a2));
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    expect(app(PourcentageEntreprise::class)->tauxApplicable($logement->refresh()))->toBe(35.0)
        ->and(app(PourcentageEntreprise::class)->tauxApplicable($autre->refresh()))->toBe(20.0);
});

it('retire une dérogation en proposant un taux nul : le logement revient au taux global', function (): void {
    $a1 = connecteComme(Profil::Administrateur);
    $a2 = User::factory()->profil(Profil::Administrateur)->create();
    $logement = Logement::factory()->create();
    $logement->forceFill(['pourcentage_entreprise_derogation' => 35.0])->save();

    $reponse = test()->postJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/pourcentage-entreprise", [])
        ->assertCreated();

    $id = $reponse->json('data.derogation_en_attente.id');
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($a2));
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    expect($logement->refresh()->pourcentage_entreprise_derogation)->toBeNull();
});

it('ferme la dérogation aux gestionnaires', function (): void {
    $gestionnaire = connecteComme(Profil::Gestionnaire);
    $logement = Logement::factory()->create();
    $gestionnaire->residences()->attach($logement->residence_id);

    test()->postJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/pourcentage-entreprise", ['taux' => 35])
        ->assertForbidden();
});

it('liste le bandeau des dérogations en vigueur, avec le taux global de comparaison', function (): void {
    connecteComme(Profil::Administrateur);
    $avecDerogation = Logement::factory()->create();
    $avecDerogation->forceFill(['pourcentage_entreprise_derogation' => 35.0])->save();
    Logement::factory()->create(); // sans dérogation : absent du bandeau

    $reponse = test()->getJson('/api/v1/backoffice/tarification/derogations')->assertOk();

    expect($reponse->json('data.derogations'))->toHaveCount(1)
        ->and($reponse->json('data.derogations.0.logement_id'))->toBe($avecDerogation->id)
        ->and((float) $reponse->json('data.derogations.0.taux_derogation'))->toBe(35.0)
        ->and((float) $reponse->json('data.derogations.0.taux_global'))->toBe(20.0);
});

// ---------------------------------------------------------------- effet sur le prix conseillé

it('utilise la dérogation du logement plutôt que le taux global dans le prix de vente conseillé', function (): void {
    $logement = Logement::factory()->create(['prix_proprietaire' => 100000]);
    $logement->forceFill(['pourcentage_entreprise_derogation' => 50.0])->save();

    $situation = app(PrixDeLogement::class)->situation($logement);

    expect($situation['pourcentage_entreprise'])->toBe(50.0)
        ->and($situation['prix_de_vente_conseille'])->toBe(150000);
});

it('même auteur qui propose et valide : refusé, comme pour le prix de vente', function (): void {
    $auteur = connecteComme(Profil::Administrateur);

    $id = test()->postJson('/api/v1/backoffice/tarification/pourcentage-entreprise', ['taux' => 25])->json('data.id');

    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertStatus(403)->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');
});
