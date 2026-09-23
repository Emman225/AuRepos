<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Services\ControleDesTarifs;
use App\Domain\Catalogue\Services\PublicationDeLogement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Referentiels\Models\TypeLogement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function typeAvecPrix(array $prix): TypeLogement
{
    static $n = 0;
    $n++;
    $type = TypeLogement::create(['code' => "test-mediane-{$n}", 'nom' => "Type de test {$n}", 'nombre_pieces' => 3]);
    foreach ($prix as $p) {
        Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => $p]);
    }

    return $type;
}

// ---------------------------------------------------------------- calcul de la médiane

it('calcule la médiane sur un nombre impair de prix', function (): void {
    $type = typeAvecPrix([10000, 30000, 20000]);

    expect(app(ControleDesTarifs::class)->medianeDuType($type->id))->toBe(20000);
});

it('calcule la médiane sur un nombre pair de prix, par moyenne des deux du milieu', function (): void {
    $type = typeAvecPrix([10000, 20000, 30000, 40000]);

    expect(app(ControleDesTarifs::class)->medianeDuType($type->id))->toBe(25000);
});

it('ignore les logements sans prix propriétaire arrêté', function (): void {
    $type = typeAvecPrix([20000, 20000]);
    Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => null]);

    expect(app(ControleDesTarifs::class)->medianeDuType($type->id))->toBe(20000);
});

it('rend null quand aucun logement du type n’a de prix arrêté', function (): void {
    $type = TypeLogement::create(['code' => 'test-mediane-vide', 'nom' => 'Type vide', 'nombre_pieces' => 2]);

    expect(app(ControleDesTarifs::class)->medianeDuType($type->id))->toBeNull();
});

// ---------------------------------------------------------------- écart d'un logement

it('exclut le logement lui-même du calcul de sa propre référence', function (): void {
    $type = typeAvecPrix([20000, 20000]);
    $logement = Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => 999999]);

    $ecart = app(ControleDesTarifs::class)->ecart($logement);

    expect($ecart['mediane_du_type'])->toBe(20000); // et non un chiffre tiré par le prix aberrant lui-même
});

it('rend null tant que le logement n’a pas de prix propriétaire', function (): void {
    typeAvecPrix([20000, 20000]);
    $logement = Logement::factory()->create();

    expect(app(ControleDesTarifs::class)->ecart($logement))->toBeNull();
});

it('signale un tarif hors médiane au-delà du seuil paramétré, jamais en-dessous', function (): void {
    $admin = User::factory()->profil(Profil::SuperAdministrateur)->create();
    app(Parametres::class)->enregistrer('proprietaires', ['ecart_mediane_seuil' => 30], $admin);

    $type = typeAvecPrix([20000, 20000]);
    $proche = Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => 25000]);   // +25 % : sous le seuil
    $eloigne = Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => 30000]);  // +50 % : au-delà

    expect(app(ControleDesTarifs::class)->ecart($proche))
        ->toMatchArray(['ecart_pourcent' => 25.0, 'hors_mediane' => false]);
    expect(app(ControleDesTarifs::class)->ecart($eloigne))
        ->toMatchArray(['ecart_pourcent' => 50.0, 'hors_mediane' => true]);
});

it('signale aussi un tarif ANORMALEMENT BAS, pas seulement élevé', function (): void {
    $type = typeAvecPrix([20000, 20000]);
    $bas = Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => 5000]); // -75 %

    expect(app(ControleDesTarifs::class)->ecart($bas))
        ->toMatchArray(['ecart_pourcent' => -75.0, 'hors_mediane' => true]);
});

// ---------------------------------------------------------------- un signal, jamais un blocage

it('un tarif hors médiane ne bloque ni la soumission ni la publication', function (): void {
    $type = typeAvecPrix([20000, 20000]);
    $proprietaire = Proprietaire::factory()->create();
    $logement = Logement::factory()->create(['type_logement_id' => $type->id, 'prix_proprietaire' => 500000]);
    $logement->residence->update(['proprietaire_id' => $proprietaire->id]);
    $logement->forceFill(['prix_vente' => 600000])->save();
    // Photos et couverture minimales pour ne buter sur aucun AUTRE obstacle.
    $minimum = (int) app(Parametres::class)->valeur('proprietaires.photos_minimum');
    foreach (range(1, $minimum) as $n) {
        PhotoLogement::create([
            'logement_id' => $logement->id, 'chemin_original' => "o{$n}", 'chemin_affichage' => "a{$n}", 'chemin_vignette' => "v{$n}",
            'ordre' => $n, 'couverture' => $n === 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000,
        ]);
    }

    expect(app(PublicationDeLogement::class)->obstacles($logement))->toBe([]);
});

// ---------------------------------------------------------------- exposition aux écrans

it('expose le contrôle médiane dans la situation des prix', function (): void {
    connecteAdministrateur();
    $type = typeAvecPrix([20000, 20000]);
    $logement = Logement::factory()->create(['type_logement_id' => $type->id]);
    $logement->forceFill(['prix_proprietaire' => 30000])->save();

    $reponse = test()->getJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/prix")->assertOk();

    // Prix à 30 000 F pour une médiane à 20 000 F : +50 %, au-delà du seuil par défaut (30 %).
    expect($reponse->json('data.controle_mediane.mediane_du_type'))->toBe(20000)
        ->and($reponse->json('data.controle_mediane.hors_mediane'))->toBeTrue();
});

it('expose le contrôle médiane sur l’écran de publication', function (): void {
    connecteAdministrateur();
    $type = typeAvecPrix([20000, 20000]);
    $logement = Logement::factory()->create(['type_logement_id' => $type->id]);
    $logement->forceFill(['prix_proprietaire' => 30000])->save();

    test()->getJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/publication")
        ->assertOk()->assertJsonPath('data.controle_mediane.mediane_du_type', 20000);
});

function connecteAdministrateur(): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil(Profil::Administrateur)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}
