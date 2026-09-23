<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Support\Api\ReponseApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function connecterComme(Profil $profil): User
{
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

function enregistrer(string $onglet, array $valeurs)
{
    return test()->putJson("/api/v1/backoffice/parametres/{$onglet}", ['valeurs' => $valeurs]);
}

it('rend les valeurs par défaut du cahier des charges tant que rien n’est modifié', function (): void {
    $p = app(Parametres::class);

    expect($p->valeur('taxes.tva'))->toBe(18.0)
        ->and($p->valeur('taxes.tdt'))->toBe(3.0)
        ->and($p->valeur('taxes.retenue_personne_physique'))->toBe(7.5)
        ->and($p->valeur('taxes.retenue_entreprise_hors_reel'))->toBe(2.0)
        ->and($p->valeur('general.plafond_paiement_en_ligne'))->toBe(2000000)
        ->and($p->valeur('general.fidelite_montant_par_point'))->toBe(1000)
        ->and($p->valeur('general.site_en_construction'))->toBeFalse()
        ->and($p->valeur('entreprise.ncc'))->toBeNull();
});

it('refuse une clé inconnue plutôt que de rendre une valeur au hasard', function (): void {
    expect(fn () => app(Parametres::class)->valeur('taxes.inconnue'))->toThrow(InvalidArgumentException::class);
});

it('réserve l’écran Paramètres aux administrateurs', function (Profil $profil, int $statut): void {
    connecterComme($profil);

    test()->getJson('/api/v1/backoffice/parametres')->assertStatus($statut);
})->with([
    [Profil::SuperAdministrateur, 200], [Profil::Administrateur, 200],
    [Profil::Gestionnaire, 403], [Profil::Gouvernante, 403], [Profil::Client, 403],
]);

it('présente les neuf onglets du lot 1 avec leurs paramètres typés', function (): void {
    connecterComme(Profil::Administrateur);

    $onglets = test()->getJson('/api/v1/backoffice/parametres')->assertOk()->json('data.onglets');

    expect(array_column($onglets, 'code'))->toBe([
        'general', 'sejours', 'taxes', 'gestionnaires', 'messages', 'comptant', 'proprietaires', 'conditions', 'entreprise',
    ]);
    $tva = collect($onglets[2]['parametres'])->firstWhere('cle', 'taxes.tva');
    expect($tva)->toMatchArray(['nom' => 'tva', 'type' => 'decimal', 'valeur' => 18, 'double_validation' => false]);

    // Le pourcentage entreprise se propose depuis Tarification (double validation, CdC § 7.3) :
    // l'écran Paramètres doit savoir qu'il ne peut pas l'enregistrer lui-même.
    $proprietaires = collect($onglets)->firstWhere('code', 'proprietaires')['parametres'];
    $pourcentage = collect($proprietaires)->firstWhere('cle', 'proprietaires.pourcentage_entreprise');
    expect($pourcentage['double_validation'])->toBeTrue();
});

it('enregistre un onglet, change la valeur lue, et trace qui a changé quoi', function (): void {
    $admin = connecterComme(Profil::Administrateur);

    enregistrer('taxes', ['tva' => 9, 'sejour_montant' => 500])->assertOk();

    expect(app(Parametres::class)->valeur('taxes.tva'))->toBe(9.0)
        ->and(app(Parametres::class)->valeur('taxes.sejour_montant'))->toBe(500)
        // un paramètre non envoyé ne bouge pas
        ->and(app(Parametres::class)->valeur('taxes.tdt'))->toBe(3.0);

    $trace = EntreeAudit::where('sujet_type', 'Parametre')->where('recit', 'like', '%TVA%')->sole();
    expect($trace->user_id)->toBe($admin->id)->and($trace->recit)->toContain('Taux de TVA');
});

it('refuse les valeurs absurdes, en français', function (array $valeurs, string $onglet, string $champ): void {
    connecterComme(Profil::Administrateur);

    $reponse = enregistrer($onglet, $valeurs)->assertStatus(422);

    expect($reponse->json('errors'))->toHaveKey("valeurs.{$champ}")
        ->and($reponse->getContent())->not->toContain('validation.');
})->with([
    'TVA au-dessus de 100 %' => [['tva' => 150], 'taxes', 'tva'],
    'TVA négative' => [['tva' => -1], 'taxes', 'tva'],
    'heure mal formée' => [['heure_arrivee' => '25h'], 'sejours', 'heure_arrivee'],
    'mode de vente inconnu' => [['vente_par_defaut' => 'autre'], 'sejours', 'vente_par_defaut'],
    'moins de photos au maximum qu’au minimum' => [['photos_maximum' => 3], 'proprietaires', 'photos_maximum'],
]);

it('refuse un onglet inconnu', function (): void {
    connecterComme(Profil::Administrateur);

    enregistrer('inexistant', ['x' => 1])->assertStatus(422);
});

it('n’accepte comme validants que deux administrateurs actifs et distincts', function (): void {
    connecterComme(Profil::SuperAdministrateur);
    $admin1 = User::factory()->profil(Profil::Administrateur)->create();
    $admin2 = User::factory()->profil(Profil::Administrateur)->create();
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    $bloque = User::factory()->profil(Profil::Administrateur)->bloque()->create();

    enregistrer('gestionnaires', ['validant_1_id' => $gestionnaire->id])->assertStatus(422);
    enregistrer('gestionnaires', ['validant_1_id' => $bloque->id])->assertStatus(422);
    enregistrer('gestionnaires', ['validant_1_id' => $admin1->id, 'validant_2_id' => $admin1->id])->assertStatus(422);

    enregistrer('gestionnaires', ['validant_1_id' => $admin1->id])->assertOk();
    // Même envoyé seul, le validant 2 ne peut pas être le validant 1 déjà enregistré.
    enregistrer('gestionnaires', ['validant_2_id' => $admin1->id])->assertStatus(422);
    enregistrer('gestionnaires', ['validant_2_id' => $admin2->id])->assertOk();
});

it('signale en alerte l’absence de trésorier et de NCC, puis les lève', function (): void {
    connecterComme(Profil::SuperAdministrateur);
    $tresorier = User::factory()->profil(Profil::Administrateur)->create();

    expect(test()->getJson('/api/v1/backoffice/parametres')->json('data.alertes'))->toHaveCount(2)
        ->and(app(Parametres::class)->tresorierDesigne())->toBeFalse();

    enregistrer('gestionnaires', ['validant_2_id' => $tresorier->id])->assertOk();
    enregistrer('entreprise', ['ncc' => '1234567A'])->assertOk();

    expect(test()->getJson('/api/v1/backoffice/parametres')->json('data.alertes'))->toBe([])
        ->and(app(Parametres::class)->tresorierDesigne())->toBeTrue();
});

it('réserve le mode « site en construction » au super administrateur', function (): void {
    connecterComme(Profil::Administrateur);
    enregistrer('general', ['site_en_construction' => true])->assertForbidden();
    expect(app(Parametres::class)->valeur('general.site_en_construction'))->toBeFalse();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    connecterComme(Profil::SuperAdministrateur);
    enregistrer('general', ['site_en_construction' => true])->assertOk();
    expect(app(Parametres::class)->valeur('general.site_en_construction'))->toBeTrue();
});

it('ferme le site au public mais pas au personnel quand il est en construction', function (): void {
    Route::middleware(['api', 'site.ouvert'])->get('api/v1/_essai/catalogue', fn () => ReponseApi::succes('ouvert'));
    $super = User::factory()->profil(Profil::SuperAdministrateur)->create();
    app(Parametres::class)->enregistrer('general', ['site_en_construction' => true], $super);

    test()->getJson('/api/v1/_essai/catalogue')->assertStatus(503)
        ->assertJsonPath('message', 'Le site est en construction. Revenez dans un instant.');

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Client)->create()))
        ->getJson('/api/v1/_essai/catalogue')->assertStatus(503);

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()))
        ->getJson('/api/v1/_essai/catalogue')->assertOk();

    // La connexion et la configuration restent ouvertes : le personnel doit pouvoir entrer.
    expect(test()->getJson('/api/v1/configuration')->assertOk()->json('data')['general.site_en_construction'])->toBeTrue();
});

it('n’expose sans connexion que les paramètres publics', function (): void {
    $data = test()->getJson('/api/v1/configuration')->assertOk()->json('data');

    expect($data)->toHaveKeys(['general.devise', 'general.plafond_paiement_en_ligne', 'sejours.heure_arrivee'])
        ->not->toHaveKeys(['taxes.retenue_personne_physique', 'entreprise.references_bancaires', 'gestionnaires.validant_2_id']);
});
