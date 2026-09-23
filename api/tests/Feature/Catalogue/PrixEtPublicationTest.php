<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Validation\Models\ChangementAValider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function agirEn(User|Profil $qui): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = $qui instanceof User ? $qui : User::factory()->profil($qui)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

function adresse(Logement $l, string $suite = ''): string
{
    return "/api/v1/backoffice/residences/{$l->residence_id}/logements/{$l->id}{$suite}";
}

function avecPhotos(Logement $logement, int $nombre = 10): Logement
{
    foreach (range(1, $nombre) as $n) {
        PhotoLogement::create([
            'logement_id' => $logement->id, 'chemin_original' => "o{$n}", 'chemin_affichage' => "a{$n}", 'chemin_vignette' => "v{$n}",
            'legende' => "Pièce {$n}", 'ordre' => $n, 'couverture' => $n === 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000,
        ]);
    }

    return $logement;
}

/** Deux administrateurs fixent le prix de vente par le vrai circuit. */
function fixerLePrixDeVente(Logement $logement, int $montant): void
{
    agirEn(Profil::Administrateur);
    test()->postJson(adresse($logement, '/prix/vente'), ['montant' => $montant])->assertCreated();
    agirEn(Profil::Administrateur);
    test()->putJson('/api/v1/backoffice/changements/'.ChangementAValider::latest('id')->value('id').'/decision', ['decision' => 'valider'])->assertOk();
}

// ---------------------------------------------------------------- prix propriétaire

it('garde l’historique de la négociation et n’arrête le prix qu’à l’accord', function (): void {
    $auteur = agirEn(Profil::Gestionnaire);
    $logement = Logement::factory()->create();
    $auteur->residences()->attach($logement->residence_id);

    test()->postJson(adresse($logement, '/prix/proprietaire'), ['montant' => 30000, 'nature' => 'proposition', 'commentaire' => 'Demande du propriétaire'])->assertOk();
    expect($logement->refresh()->prix_proprietaire)->toBeNull();

    $situation = test()->postJson(adresse($logement, '/prix/proprietaire'), ['montant' => 25000, 'nature' => 'accord'])->assertOk()->json('data');

    expect($logement->refresh()->prix_proprietaire)->toBe(25000)
        ->and(array_column($situation['negociation'], 'montant'))->toBe([25000, 30000])
        ->and($situation['negociation'][0]['nature'])->toBe('accord')
        // Pourcentage entreprise par défaut 20 % : plancher de marge CONSEILLÉ, pas imposé.
        ->and($situation['prix_de_vente_conseille'])->toBe(30000);
});

// ---------------------------------------------------------------- prix de vente : double validation

it('ne change le prix de vente qu’après validation par un second administrateur', function (): void {
    $logement = Logement::factory()->create();
    $premier = agirEn(Profil::Administrateur);

    $situation = test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 35000, 'motif' => 'Ouverture'])->assertCreated()->json('data');
    $id = $situation['changement_en_attente']['id'];

    // Tant que ce n'est pas validé, l'ancienne valeur reste en vigueur.
    expect($logement->refresh()->prix_vente)->toBeNull()
        ->and($situation['changement_en_attente']['je_peux_valider'])->toBeFalse()
        ->and($situation['changement_en_attente']['je_peux_annuler'])->toBeTrue();

    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertForbidden()->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');
    expect($logement->refresh()->prix_vente)->toBeNull();

    $second = agirEn(Profil::Administrateur);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk()->assertJsonPath('data.statut', 'valide');

    expect($logement->refresh()->prix_vente)->toBe(35000);
    $trace = EntreeAudit::where('action', 'changement_valide')->sole();
    expect($trace->user_id)->toBe($second->id)->and($trace->recit)->toContain($premier->nomComplet())->toContain('35 000');
});

it('fait garantir par la base qu’on ne valide pas sa propre proposition', function (): void {
    $logement = Logement::factory()->create();
    $admin = User::factory()->profil(Profil::Administrateur)->create();
    $c = ChangementAValider::create(['sujet_type' => 'logement', 'sujet_id' => $logement->id, 'champ' => 'prix_vente', 'valeur_proposee' => 1, 'propose_par' => $admin->id]);

    expect(fn () => DB::transaction(fn () => DB::table('changements_a_valider')->where('id', $c->id)->update(['statut' => 'valide', 'decide_par' => $admin->id])))
        ->toThrow(QueryException::class);
});

it('réserve la proposition et la validation du prix de vente aux administrateurs', function (): void {
    $logement = Logement::factory()->create();
    $auteur = agirEn(Profil::Gestionnaire);
    $auteur->residences()->attach($logement->residence_id);

    test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 35000])->assertForbidden();
    test()->getJson('/api/v1/backoffice/changements')->assertForbidden();
});

it('n’admet qu’une proposition en attente à la fois, que son auteur peut retirer', function (): void {
    $logement = Logement::factory()->create();
    agirEn(Profil::Administrateur);
    $id = test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 35000])->json('data.changement_en_attente.id');

    test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 40000])->assertStatus(409)->assertJsonPath('errors.code.0', 'changement_deja_en_attente');

    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'annuler'])->assertOk()->assertJsonPath('data.statut', 'annule');
    test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 40000])->assertCreated();
});

it('exige un motif pour refuser, et ne retraite pas un changement déjà décidé', function (): void {
    $logement = Logement::factory()->create();
    agirEn(Profil::Administrateur);
    $id = test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 35000])->json('data.changement_en_attente.id');

    agirEn(Profil::Administrateur);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'refuser'])->assertStatus(422);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'refuser', 'motif' => 'Trop cher pour le quartier'])->assertOk();
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertStatus(409)->assertJsonPath('errors.code.0', 'changement_deja_traite');

    expect($logement->refresh()->prix_vente)->toBeNull();
});

it('refuse de vendre à perte, à la proposition comme à la validation', function (): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['prix_proprietaire' => 25000])->save();

    agirEn(Profil::Administrateur);
    test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 20000])->assertStatus(422)->assertJsonPath('errors.code.0', 'vente_a_perte');

    // Le prix propriétaire est renégocié à la hausse ENTRE la proposition et la validation.
    $id = test()->postJson(adresse($logement, '/prix/vente'), ['montant' => 30000])->json('data.changement_en_attente.id');
    $logement->forceFill(['prix_proprietaire' => 32000])->save();

    agirEn(Profil::Administrateur);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertStatus(422)->assertJsonPath('errors.code.0', 'vente_a_perte');
    expect($logement->refresh()->prix_vente)->toBeNull();
});

it('refuse d’arrêter un prix propriétaire au-dessus du prix de vente en vigueur', function (): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['prix_vente' => 30000])->save();
    $auteur = agirEn(Profil::Gestionnaire);
    $auteur->residences()->attach($logement->residence_id);

    test()->postJson(adresse($logement, '/prix/proprietaire'), ['montant' => 31000, 'nature' => 'accord'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'vente_a_perte');
});

// ---------------------------------------------------------------- publication

it('publie un logement prêt, mais jamais par celui qui l’a saisi', function (): void {
    $saisisseur = agirEn(Profil::Administrateur);
    $residence = Residence::factory()->create();
    $type = Logement::factory()->create()->type_logement_id;
    $id = test()->postJson("/api/v1/backoffice/residences/{$residence->id}/logements", [
        'type_logement_id' => $type, 'nom' => 'Studio S1', 'nombre_pieces' => 1, 'capacite_de_base' => 1, 'capacite_maximale' => 2,
    ])->assertCreated()->json('data.id');
    $logement = avecPhotos(Logement::findOrFail($id));
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 26000])->save();

    expect($logement->getAttribute('cree_par'))->toBe($saisisseur->id);

    agirEn($saisisseur);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'soumettre'])->assertOk()->assertJsonPath('data.etat_publication', 'en_attente');
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])
        ->assertForbidden()->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');

    $autre = agirEn(Profil::Administrateur);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertOk()->assertJsonPath('data.etat_publication', 'publie');

    expect($logement->refresh()->getAttribute('publie_par'))->toBe($autre->id)
        ->and(EntreeAudit::where('action', 'publication_publie')->sole()->user_id)->toBe($autre->id);
});

it('dit en clair ce qui empêche la mise en ligne', function (): void {
    agirEn(Profil::Administrateur);
    $logement = Logement::factory()->create();

    $obstacles = test()->getJson(adresse($logement, '/publication'))->assertOk()->json('data.obstacles');
    expect($obstacles)->toHaveCount(3)
        ->and($obstacles[0])->toContain('au moins 10 photos')
        ->and(implode(' ', $obstacles))->toContain('prix de vente')->toContain('prix propriétaire');

    test()->postJson(adresse($logement, '/publication'), ['action' => 'soumettre'])->assertStatus(422)->assertJsonPath('errors.code.0', 'logement_non_pret');
});

it('ne réclame pas de prix propriétaire au compte interne de l’entreprise', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => Proprietaire::factory()->interne()]);
    $logement = avecPhotos(Logement::factory()->create(['residence_id' => $residence->id]));
    $logement->forceFill(['prix_vente' => 40000, 'etat_publication' => EtatPublication::EnAttente])->save();

    agirEn(Profil::Administrateur);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertOk();
});

it('réserve la publication au validateur désigné quand il y en a un', function (): void {
    $logement = avecPhotos(Logement::factory()->create());
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 26000, 'etat_publication' => EtatPublication::EnAttente])->save();
    $designe = User::factory()->profil(Profil::Administrateur)->create();
    app(Parametres::class)->enregistrer('proprietaires', ['validateur_publications_id' => $designe->id], $designe);

    agirEn(Profil::Administrateur);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertForbidden()->assertJsonPath('errors.code.0', 'validateur_non_designe');

    agirEn($designe);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertOk();
});

it('refuse, suspend et remet en ligne, toujours avec un motif', function (): void {
    $logement = avecPhotos(Logement::factory()->create());
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 26000, 'etat_publication' => EtatPublication::EnAttente])->save();
    agirEn(Profil::Administrateur);

    test()->postJson(adresse($logement, '/publication'), ['action' => 'refuser'])->assertStatus(422);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'refuser', 'motif' => 'Adresse incomplète'])->assertOk()->assertJsonPath('data.etat_publication', 'refuse');
    // Un logement refusé ne se publie pas directement : il se corrige puis se resoumet.
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertStatus(409);

    test()->postJson(adresse($logement, '/publication'), ['action' => 'soumettre'])->assertOk();
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertOk();
    test()->postJson(adresse($logement, '/publication'), ['action' => 'suspendre', 'motif' => 'Travaux de plomberie'])->assertOk()->assertJsonPath('data.etat_publication', 'suspendu');
    test()->postJson(adresse($logement, '/publication'), ['action' => 'reactiver'])->assertOk()->assertJsonPath('data.etat_publication', 'publie');
});

it('interdit à un gestionnaire de publier', function (): void {
    $logement = avecPhotos(Logement::factory()->create());
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 26000, 'etat_publication' => EtatPublication::EnAttente])->save();

    $auteur = agirEn(Profil::Gestionnaire);
    $auteur->residences()->attach($logement->residence_id);
    test()->postJson(adresse($logement, '/publication'), ['action' => 'publier'])->assertForbidden();
});

// ---------------------------------------------------------------- ce que voit le public

it('ne montre au public que les logements publiés', function (EtatPublication $etat, int $statut): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => $etat, 'prix_vente' => 26000])->save();

    test()->getJson("/api/v1/catalogue/logements/{$logement->refresh()->reference}")->assertStatus($statut);
})->with([
    [EtatPublication::Publie, 200], [EtatPublication::Brouillon, 404], [EtatPublication::EnAttente, 404],
    [EtatPublication::Refuse, 404], [EtatPublication::PrixANegocier, 404], [EtatPublication::Suspendu, 404],
]);

it('ne laisse JAMAIS fuiter vers le public le prix propriétaire, la marge, l’adresse ni le propriétaire', function (): void {
    $residence = Residence::factory()->create(['adresse' => 'RUE-SECRETE-45', 'consignes_acces' => 'CODE-PORTAIL-9911', 'repere' => 'REPERE-CACHE']);
    $logement = avecPhotos(Logement::factory()->create(['residence_id' => $residence->id]), 2);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_proprietaire' => 21987, 'prix_vente' => 26000])->save();

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$logement->refresh()->reference}")->assertOk();
    $corps = $reponse->getContent();

    expect($reponse->json('data.prix_par_nuit'))->toBe(26000)
        ->and($reponse->json('data.lieu'))->toBe(['commune' => 'Cocody', 'quartier' => 'Angré'])
        ->and($reponse->json('data.photos'))->toHaveCount(2)
        ->and($corps)->not->toContain('21987')            // prix propriétaire
        ->not->toContain('4013')                            // marge (26000 − 21987)
        ->not->toContain('prix_proprietaire')->not->toContain('marge')
        ->not->toContain('RUE-SECRETE-45')->not->toContain('CODE-PORTAIL-9911')->not->toContain('REPERE-CACHE')
        ->not->toContain($residence->proprietaire->utilisateur->email)
        ->not->toContain($residence->proprietaire->utilisateur->nom);
});

it('cache le catalogue public quand le site est en construction', function (): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 26000])->save();
    app(Parametres::class)->enregistrer('general', ['site_en_construction' => true], User::factory()->profil(Profil::SuperAdministrateur)->create());

    test()->getJson("/api/v1/catalogue/logements/{$logement->refresh()->reference}")->assertStatus(503);
});
