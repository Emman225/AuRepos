<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Models\VersionLogement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\NotificationGeneriqueMail;
use Database\Factories\ResidenceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->proprietaire = Proprietaire::factory()->create();
});

function agirEnQue(User|Profil $qui): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = $qui instanceof User ? $qui : User::factory()->profil($qui)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

function avecDesPhotosAcceptees(Logement $logement, int $nombre = 10): Logement
{
    foreach (range(1, $nombre) as $n) {
        PhotoLogement::create([
            'logement_id' => $logement->id, 'chemin_original' => "o{$n}", 'chemin_affichage' => "a{$n}", 'chemin_vignette' => "v{$n}",
            'legende' => "Pièce {$n}", 'ordre' => $n, 'couverture' => $n === 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000,
            'etat' => 'acceptee',
        ]);
    }

    return $logement;
}

// ---------------------------------------------------------------- P3-PUB-01 : création par le propriétaire

it('laisse le propriétaire créer sa résidence et ses logements, en brouillon, jamais visibles du public', function (): void {
    agirEnQue($this->proprietaire->utilisateur);
    $quartier = ResidenceFactory::unQuartier();

    $residence = test()->postJson('/api/v1/proprietaire/residences', [
        'quartier_id' => $quartier->id, 'nom' => 'Ma résidence', 'adresse' => 'Rue 12',
    ])->assertCreated()->json('data');

    expect(Residence::findOrFail($residence['id'])->proprietaire_id)->toBe($this->proprietaire->id);

    $type = Logement::factory()->create()->type_logement_id;
    $logement = test()->postJson("/api/v1/proprietaire/residences/{$residence['id']}/logements", [
        'type_logement_id' => $type, 'nom' => 'Studio', 'nombre_pieces' => 1, 'capacite_de_base' => 1, 'capacite_maximale' => 2,
    ])->assertCreated()->json('data');

    expect($logement['etat_publication'])->toBe(EtatPublication::Brouillon->value)
        ->and(Logement::findOrFail($logement['id'])->residence_id)->toBe($residence['id']);
});

it('empêche un propriétaire de créer un logement dans la résidence d’un autre (404)', function (): void {
    $autreResidence = Residence::factory()->create();
    agirEnQue($this->proprietaire->utilisateur);

    $type = Logement::factory()->create()->type_logement_id;
    test()->postJson("/api/v1/proprietaire/residences/{$autreResidence->id}/logements", [
        'type_logement_id' => $type, 'nom' => 'Studio', 'nombre_pieces' => 1, 'capacite_de_base' => 1, 'capacite_maximale' => 2,
    ])->assertNotFound();
});

// ---------------------------------------------------------------- P3-PUB-02 : file « Publications à valider »

it('liste dans la file « Publications à valider » tous les logements en attente, toutes résidences confondues, avec sa pastille', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $enAttente = Logement::factory()->create(['residence_id' => $residence->id]);
    $enAttente->forceFill(['etat_publication' => EtatPublication::EnAttente])->save();

    $autreResidence = Residence::factory()->create();
    $autreEnAttente = Logement::factory()->create(['residence_id' => $autreResidence->id]);
    $autreEnAttente->forceFill(['etat_publication' => EtatPublication::EnAttente])->save();

    // Un brouillon et un publié ne polluent jamais la file.
    Logement::factory()->create(['residence_id' => $residence->id]);
    $publie = Logement::factory()->create(['residence_id' => $residence->id]);
    $publie->forceFill(['etat_publication' => EtatPublication::Publie])->save();

    agirEnQue(Profil::Administrateur);
    $reponse = test()->getJson('/api/v1/backoffice/publications-a-valider')->assertOk();
    expect(array_column($reponse->json('data.elements'), 'id'))->toEqualCanonicalizing([$enAttente->id, $autreEnAttente->id]);

    test()->getJson('/api/v1/backoffice/publications-a-valider/compte')->assertOk()->assertJsonPath('data.nombre', 2);
});

// ---------------------------------------------------------------- P3-PUB-02 : refus motivé, par champ et par photo, resoumission

it('refuse avec un motif par champ, et laisse le propriétaire corriger puis resoumettre', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = avecDesPhotosAcceptees(Logement::factory()->create(['residence_id' => $residence->id, 'description' => 'trop court']));
    $logement->forceFill(['prix_proprietaire' => 20000, 'prix_vente' => 26000, 'etat_publication' => EtatPublication::EnAttente])->save();

    agirEnQue(Profil::Administrateur);
    test()->postJson("/api/v1/backoffice/residences/{$residence->id}/logements/{$logement->id}/publication", [
        'action' => 'refuser', 'motif' => 'Description trop pauvre et adresse à préciser',
        'motifs_champs' => ['description' => 'Trop courte : détaillez les équipements.'],
    ])->assertOk()->assertJsonPath('data.etat_publication', 'refuse');

    expect($logement->refresh()->motifs_refus_champs)->toBe(['description' => 'Trop courte : détaillez les équipements.']);

    agirEnQue($this->proprietaire->utilisateur);
    $vu = test()->getJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}/publication")->assertOk()->json('data');
    expect($vu['motifs_refus_champs'])->toBe(['description' => 'Trop courte : détaillez les équipements.']);

    // Le propriétaire corrige LE champ visé, puis resoumet.
    test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}", [
        'description' => 'Bel appartement climatisé avec wifi et parking privé, proche du marché.',
    ])->assertOk();
    test()->postJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}/soumission")
        ->assertOk()->assertJsonPath('data.etat_publication', 'en_attente');

    // Le motif refusé est effacé à la resoumission : plus de dossier « en attente de correction ».
    expect($logement->refresh()->motifs_refus_champs)->toBeNull();
});

it('refuse une photo précise avec son motif, sans toucher aux autres', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $photo = PhotoLogement::create([
        'logement_id' => $logement->id, 'chemin_original' => 'o', 'chemin_affichage' => 'a', 'chemin_vignette' => 'v',
        'ordre' => 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000, 'ajoutee_par_administration' => false, 'etat' => 'en_attente',
    ]);

    agirEnQue(Profil::Administrateur);
    test()->putJson("/api/v1/backoffice/residences/{$residence->id}/logements/{$logement->id}/photos/{$photo->id}/refus", [
        'motif' => 'Photo floue, à reprendre en meilleure lumière',
    ])->assertOk();

    expect($photo->refresh()->etat)->toBe('refusee')->and($photo->motif_refus)->toBe('Photo floue, à reprendre en meilleure lumière');
});

// ---------------------------------------------------------------- P3-PUB-03 : négociation du prix, côté propriétaire

it('laisse le propriétaire contre-proposer puis accepter, et empêche un accord au-dessus du prix de vente', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['prix_vente' => 30000])->save();

    agirEnQue(Profil::Administrateur);
    test()->postJson("/api/v1/backoffice/residences/{$residence->id}/logements/{$logement->id}/prix/proprietaire", [
        'montant' => 28000, 'nature' => 'proposition',
    ])->assertOk();

    agirEnQue($this->proprietaire->utilisateur);
    // Le propriétaire refuse ce montant et contre-propose plus haut que le prix de vente.
    test()->postJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}/negociation", [
        'montant' => 31000, 'nature' => 'accord',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'vente_a_perte');

    $situation = test()->postJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}/negociation", [
        'montant' => 26000, 'nature' => 'accord', 'commentaire' => 'Accepté',
    ])->assertOk()->json('data');

    expect($logement->refresh()->prix_proprietaire)->toBe(26000)
        ->and($situation['negociation'][0]['partie'])->toBe('proprietaire')
        ->and($situation['negociation'][1]['partie'])->toBe('administration');
});

it('n’ouvre la négociation d’un logement qu’à son propre propriétaire', function (): void {
    $autreLogement = Logement::factory()->create();
    agirEnQue($this->proprietaire->utilisateur);

    test()->postJson("/api/v1/proprietaire/residences/{$autreLogement->residence_id}/logements/{$autreLogement->id}/negociation", [
        'montant' => 20000, 'nature' => 'proposition',
    ])->assertNotFound();
});

// ---------------------------------------------------------------- P3-PUB-04 : modification d'un logement publié

it('n’applique pas en place la modification d’un logement PUBLIÉ : une version attend sa validation', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id, 'description' => 'Ancienne description']);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie])->save();

    agirEnQue($this->proprietaire->utilisateur);
    $reponse = test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}", [
        'description' => 'Nouvelle description, wifi et climatisation ajoutés',
    ])->assertCreated()->json('data');

    // La version PUBLIÉE reste en ligne, telle quelle, tant que rien n'est validé.
    expect($logement->refresh()->description)->toBe('Ancienne description')
        ->and($logement->etat_publication)->toBe(EtatPublication::Publie)
        ->and($reponse['statut'])->toBe('en_attente');

    $version = VersionLogement::findOrFail($reponse['id']);
    $administrateur = agirEnQue(Profil::Administrateur);
    test()->putJson("/api/v1/backoffice/residences/{$residence->id}/logements/{$logement->id}/versions/{$version->id}/validation")
        ->assertOk();

    expect($logement->refresh()->description)->toBe('Nouvelle description, wifi et climatisation ajoutés')
        ->and($logement->etat_publication)->toBe(EtatPublication::Publie);
});

it('n’admet qu’une seule version en attente à la fois sur un logement publié', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie])->save();

    agirEnQue($this->proprietaire->utilisateur);
    test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}", ['description' => 'Première proposition'])->assertCreated();
    test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}", ['description' => 'Seconde proposition'])
        ->assertStatus(409)->assertJsonPath('errors.code.0', 'version_deja_en_attente');
});

it('modifie directement un logement encore en brouillon : aucune version n’est nécessaire', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id, 'description' => 'Brouillon']);

    agirEnQue($this->proprietaire->utilisateur);
    test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}", ['description' => 'Corrigé'])->assertOk();

    expect($logement->refresh()->description)->toBe('Corrigé')
        ->and(VersionLogement::count())->toBe(0);
});

// ---------------------------------------------------------------- P3-PUB-05 : droits sur les photos

it('interdit au propriétaire de supprimer une photo ajoutée par l’administration', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $photo = PhotoLogement::create([
        'logement_id' => $logement->id, 'chemin_original' => 'o', 'chemin_affichage' => 'a', 'chemin_vignette' => 'v',
        'ordre' => 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000, 'ajoutee_par_administration' => true, 'etat' => 'acceptee',
    ]);

    agirEnQue($this->proprietaire->utilisateur);
    test()->deleteJson("/api/v1/proprietaire/residences/{$residence->id}/logements/{$logement->id}/photos/{$photo->id}", ['motif' => 'Je ne veux plus de cette photo'])
        ->assertForbidden();

    expect(PhotoLogement::find($photo->id))->not->toBeNull();
});

it('notifie le propriétaire quand l’administration supprime SA photo, et journalise le motif', function (): void {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $photo = PhotoLogement::create([
        'logement_id' => $logement->id, 'chemin_original' => 'o', 'chemin_affichage' => 'a', 'chemin_vignette' => 'v',
        'ordre' => 1, 'largeur' => 1600, 'hauteur' => 1067, 'taille_octets' => 1000, 'ajoutee_par_administration' => false, 'etat' => 'acceptee',
    ]);

    agirEnQue(Profil::Administrateur);
    test()->deleteJson("/api/v1/backoffice/residences/{$residence->id}/logements/{$logement->id}/photos/{$photo->id}", ['motif' => 'Photo non conforme, doublon'])
        ->assertOk();

    expect(PhotoLogement::find($photo->id))->toBeNull();
    Mail::assertQueued(NotificationGeneriqueMail::class, fn ($m) => $m->hasTo($this->proprietaire->utilisateur->email) && str_contains($m->corps, 'Photo non conforme, doublon'));
});

// ---------------------------------------------------------------- P3-PUB-06 : bouton Occupée / Disponible

it('ferme la résidence d’un clic, avertit des séjours confirmés à honorer, et journalise l’historique', function (): void {
    $residence = Residence::factory()->create(['proprietaire_id' => $this->proprietaire->id]);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'client_id' => User::factory()->create()->id,
        'arrivee' => Carbon::today()->addDays(2)->toDateString(), 'depart' => Carbon::today()->addDays(5)->toDateString(),
    ]);

    agirEnQue($this->proprietaire->utilisateur);
    $reponse = test()->putJson("/api/v1/proprietaire/residences/{$residence->id}/disponibilite", [
        'disponibilite' => 'occupee', 'motif' => 'Usage personnel', 'reouverture_prevue_le' => Carbon::today()->addDays(10)->toDateString(),
    ])->assertOk()->json('data');

    expect($reponse['disponibilite'])->toBe('occupee')->and($reponse['sejours_a_honorer'])->toBe(1)
        ->and($residence->refresh()->disponibilite)->toBe(Disponibilite::Occupee);

    // Le taux se mesure sur la durée RÉELLEMENT fermée : on avance le temps de quelques jours fermés
    // (et on se reconnecte : le jeton émis avant le saut dans le temps aurait expiré).
    Carbon::setTestNow(Carbon::now()->addDays(3));
    agirEnQue($this->proprietaire->utilisateur);

    $historique = test()->getJson("/api/v1/proprietaire/residences/{$residence->id}/disponibilite/historique")->assertOk()->json('data');
    expect($historique['historique'])->toHaveCount(1)
        ->and($historique['historique'][0]['motif'])->toBe('Usage personnel')
        ->and($historique['historique'][0]['en_cours'])->toBeTrue()
        ->and($historique['taux_disponibilite_12_mois'])->toBeLessThan(100.0);

    Carbon::setTestNow();
});

it('empêche un propriétaire de fermer la résidence d’un autre (404)', function (): void {
    $autreResidence = Residence::factory()->create();
    agirEnQue($this->proprietaire->utilisateur);

    test()->putJson("/api/v1/proprietaire/residences/{$autreResidence->id}/disponibilite", ['disponibilite' => 'occupee'])->assertNotFound();
});
