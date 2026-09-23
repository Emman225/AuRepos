<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Tarification\Models\CodePromo;
use App\Domain\Tarification\Models\PrixNegocie;
use App\Domain\Tarification\Services\CodesPromo;
use App\Domain\Tarification\Services\PrixNegocies;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
});

function admin(): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil(Profil::Administrateur)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- service : prix négociés

it('n’admet qu’une négociation par client et par type : la nouvelle remplace l’ancienne', function (): void {
    $client = User::factory()->create();
    $type = TypeLogement::create(['code' => 'pn-t1', 'nom' => 'Type', 'nombre_pieces' => 3]);
    $auteur = User::factory()->profil(Profil::Administrateur)->create();

    app(PrixNegocies::class)->enregistrer($client, $type->id, 25000, 'Première négociation', $auteur);
    app(PrixNegocies::class)->enregistrer($client, $type->id, 22000, 'Ajustement', $auteur);

    expect(PrixNegocie::count())->toBe(1)
        ->and(app(PrixNegocies::class)->pour($client, $type->id))->toBe(22000);
});

it('rend null quand il n’y a pas de négociation, ou qu’elle est désactivée', function (): void {
    $client = User::factory()->create();
    $type = TypeLogement::create(['code' => 'pn-t2', 'nom' => 'Type', 'nombre_pieces' => 3]);

    expect(app(PrixNegocies::class)->pour($client, $type->id))->toBeNull();

    $negociation = app(PrixNegocies::class)->enregistrer($client, $type->id, 25000, null, User::factory()->profil(Profil::Administrateur)->create());
    app(PrixNegocies::class)->desactiver($negociation);

    expect(app(PrixNegocies::class)->pour($client, $type->id))->toBeNull();
});

// ---------------------------------------------------------------- service : codes promo

it('vérifie un code promo valide, insensible à la casse', function (): void {
    $residence = Residence::factory()->create();
    CodePromo::create([
        'code' => 'ETE2026', 'type' => 'pourcentage', 'valeur' => 10,
        'date_debut' => '2026-09-01', 'date_fin' => '2026-10-31', 'cree_par' => admin()->id,
    ]);

    $codePromo = app(CodesPromo::class)->verifier('ete2026', $residence);

    expect($codePromo->code)->toBe('ETE2026');
});

it('refuse un code inconnu, expiré, inactif, ou d’une autre résidence — avec un motif clair', function (): void {
    $residenceA = Residence::factory()->create();
    $residenceB = Residence::factory()->create();
    $auteur = admin();

    $expire = CodePromo::create(['code' => 'EXPIRE', 'type' => 'montant', 'valeur' => 5000, 'date_debut' => '2026-01-01', 'date_fin' => '2026-01-31', 'cree_par' => $auteur->id]);
    $inactif = CodePromo::create(['code' => 'INACTIF', 'type' => 'montant', 'valeur' => 5000, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-31', 'actif' => false, 'cree_par' => $auteur->id]);
    $autreResidence = CodePromo::create(['code' => 'AUTRE', 'type' => 'montant', 'valeur' => 5000, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-31', 'residence_id' => $residenceB->id, 'cree_par' => $auteur->id]);

    expect(fn () => app(CodesPromo::class)->verifier('INCONNU', $residenceA))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_promo_inconnu'));
    expect(fn () => app(CodesPromo::class)->verifier('EXPIRE', $residenceA))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_promo_hors_periode'));
    expect(fn () => app(CodesPromo::class)->verifier('INACTIF', $residenceA))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_promo_inactif'));
    expect(fn () => app(CodesPromo::class)->verifier('AUTRE', $residenceA))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('code_promo_residence_invalide'));
});

it('calcule la réduction en pourcentage et en montant, plafonnée à la base', function (): void {
    $pourcentage = CodePromo::make(['type' => 'pourcentage', 'valeur' => 10]);
    $montant = CodePromo::make(['type' => 'montant', 'valeur' => 50000]);
    $montantExcessif = CodePromo::make(['type' => 'montant', 'valeur' => 999999]);

    expect(app(CodesPromo::class)->calculerLaReduction($pourcentage, 100000))->toBe(10000)
        ->and(app(CodesPromo::class)->calculerLaReduction($montant, 100000))->toBe(50000)
        ->and(app(CodesPromo::class)->calculerLaReduction($montantExcessif, 100000))->toBe(100000); // jamais plus que la base
});

// ---------------------------------------------------------------- intégration : réservation

function logementPublie(): Logement
{
    $type = TypeLogement::firstOrCreate(['code' => 'pn-int'], ['nom' => 'Type intégration', 'nombre_pieces' => 3]);
    $logement = Logement::factory()->create(['type_logement_id' => $type->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    return $logement->refresh();
}

it('ignore la grille : le prix négocié du client prime, comme le dit le CdC', function (): void {
    $client = User::factory()->create();
    $logement = logementPublie();
    app(PrixNegocies::class)->enregistrer($client, $logement->type_logement_id, 24000, null, admin());

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($client));

    $reponse = test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-12',
        'adultes' => 2, 'mode_reglement' => 'agence',
    ])->assertCreated();

    // 2 nuits à 24 000 F négociés, et non 30 000 F de la grille.
    expect($reponse->json('data.devis.hebergement_brut_ht'))->toBe(48000);
});

it('applique un code promo valide à la réservation, et le garde dans le devis figé', function (): void {
    $client = User::factory()->create();
    $logement = logementPublie();
    CodePromo::create([
        'code' => 'BIENVENUE', 'type' => 'pourcentage', 'valeur' => 10,
        'date_debut' => '2026-09-01', 'date_fin' => '2026-12-31', 'cree_par' => admin()->id,
    ]);

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($client));

    $reponse = test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'agence', 'code_promo' => 'bienvenue',
    ])->assertCreated();

    // 3 nuits à 30 000 F = 90 000 F HT, moins 10 % = 9 000 F.
    expect($reponse->json('data.devis.remise_ht'))->toBe(0) // la remise administrateur, distincte de la réduction
        ->and($reponse->json('data.devis.reductions.0.libelle'))->toContain('BIENVENUE')
        ->and($reponse->json('data.devis.reductions.0.montant'))->toBe(9000);

    $sejour = Sejour::where('reference', $reponse->json('data.reference'))->firstOrFail();
    expect($sejour->devis['reductions'][0]['montant'])->toBe(9000); // figé, même si le code change après
});

it('refuse la réservation avec un motif clair quand le code promo est invalide', function (): void {
    $client = User::factory()->create();
    $logement = logementPublie();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($client));

    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'agence', 'code_promo' => 'INCONNU',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'code_promo_inconnu');
});

// ---------------------------------------------------------------- intégration : estimation publique

it('affiche le prix négocié et l’aperçu du code promo dans l’estimation publique', function (): void {
    $client = User::factory()->create();
    $logement = logementPublie();
    app(PrixNegocies::class)->enregistrer($client, $logement->type_logement_id, 24000, null, admin());
    CodePromo::create(['code' => 'PROMO', 'type' => 'montant', 'valeur' => 5000, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-31', 'cree_par' => admin()->id]);

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($client));

    $reponse = test()->postJson("/api/v1/catalogue/logements/{$logement->reference}/estimation", [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-12', 'adultes' => 2, 'code_promo' => 'promo',
    ])->assertOk();

    expect($reponse->json('data.hebergement_brut_ht'))->toBe(48000) // 2 nuits négociées à 24 000 F
        ->and($reponse->json('data.code_promo.valide'))->toBeTrue()
        ->and($reponse->json('data.reductions.0.montant'))->toBe(5000);
});

it('renvoie un motif en clair pour un code promo invalide, sans faire échouer l’estimation', function (): void {
    $logement = logementPublie();

    $reponse = test()->postJson("/api/v1/catalogue/logements/{$logement->reference}/estimation", [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-12', 'adultes' => 2, 'code_promo' => 'INCONNU',
    ])->assertOk();

    expect($reponse->json('data.code_promo.valide'))->toBeFalse()
        ->and($reponse->json('data.code_promo.motif'))->toContain('inconnu');
});

// ---------------------------------------------------------------- back-office

it('crée, liste et modifie un code promo par le back office', function (): void {
    admin();
    $residence = Residence::factory()->create();

    $id = test()->postJson('/api/v1/backoffice/codes-promo', [
        'code' => 'noel2026', 'type' => 'pourcentage', 'valeur' => 15,
        'date_debut' => '2026-12-01', 'date_fin' => '2026-12-31', 'residence_id' => $residence->id,
    ])->assertCreated()->json('data.id');

    test()->getJson('/api/v1/backoffice/codes-promo')->assertOk()->assertJsonCount(1, 'data.elements');

    test()->putJson("/api/v1/backoffice/codes-promo/{$id}", ['actif' => false])
        ->assertOk()->assertJsonPath('data.actif', false)->assertJsonPath('data.code', 'NOEL2026'); // majuscules forcées
});

it('renvoie « actif » à vrai dans la réponse de création, sans attendre un rechargement', function (): void {
    admin();

    // Défaut posé par la base (colonne DEFAULT true), pas envoyé dans la saisie : le modèle
    // fraîchement créé doit déjà le connaître (règle du projet, trois défauts similaires déjà trouvés).
    test()->postJson('/api/v1/backoffice/codes-promo', [
        'code' => 'DEFAUT', 'type' => 'montant', 'valeur' => 1000, 'date_debut' => '2026-09-01', 'date_fin' => '2026-10-31',
    ])->assertCreated()->assertJsonPath('data.actif', true)->assertJsonPath('data.valable_aujourd_hui', true);
});

it('refuse un pourcentage au-delà de 100', function (): void {
    admin();

    test()->postJson('/api/v1/backoffice/codes-promo', [
        'code' => 'TROP', 'type' => 'pourcentage', 'valeur' => 150, 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30',
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['valeur']]);
});

it('crée et désactive un prix négocié par le back office', function (): void {
    $auteur = admin();
    $client = User::factory()->create();
    $type = TypeLogement::create(['code' => 'pn-bo', 'nom' => 'Type BO', 'nombre_pieces' => 2]);

    $id = test()->postJson('/api/v1/backoffice/prix-negocies', [
        'client_id' => $client->id, 'type_logement_id' => $type->id, 'tarif_par_nuit' => 20000,
    ])->assertCreated()->json('data.id');

    test()->putJson("/api/v1/backoffice/prix-negocies/{$id}/desactivation")
        ->assertOk()->assertJsonPath('data.actif', false);

    expect(app(PrixNegocies::class)->pour($client, $type->id))->toBeNull();
});

it('exporte la liste des codes promo en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    $auteur = admin();
    CodePromo::create(['code' => 'ETE2026', 'type' => 'pourcentage', 'valeur' => 10, 'date_debut' => '2026-09-01', 'date_fin' => '2026-10-31', 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/backoffice/codes-promo/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /codes-promo/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    admin();

    test()->getJson('/api/v1/backoffice/codes-promo/export?format=xlsx')->assertOk();
});

it('exporte la liste des prix négociés en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    $auteur = admin();
    $client = User::factory()->create();
    $type = TypeLogement::create(['code' => 'pn-export', 'nom' => 'Type export', 'nombre_pieces' => 2]);
    app(PrixNegocies::class)->enregistrer($client, $type->id, 20000, null, $auteur);

    $reponse = test()->getJson('/api/v1/backoffice/prix-negocies/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /prix-negocies/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    admin();

    test()->getJson('/api/v1/backoffice/prix-negocies/export?format=xlsx')->assertOk();
});

it('ferme la gestion des prix négociés et des codes promo aux gestionnaires', function (): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()));

    test()->getJson('/api/v1/backoffice/codes-promo')->assertForbidden();
    test()->getJson('/api/v1/backoffice/prix-negocies')->assertForbidden();
});
