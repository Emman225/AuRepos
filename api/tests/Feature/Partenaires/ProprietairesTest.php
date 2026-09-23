<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Partenaires\Services\PiecesJustificatives;
use App\Domain\Partenaires\Services\RetenueALaSource;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

function seConnecterEn(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

function piece(Proprietaire $p, TypeDePiece $type, StatutDePiece $statut = StatutDePiece::Validee, ?string $expire = null): PieceJustificative
{
    return PieceJustificative::create([
        'titulaire_type' => $p->getMorphClass(), 'titulaire_id' => $p->id, 'type' => $type, 'chemin' => 'x',
        'nom_original' => 'piece.pdf', 'mime' => 'application/pdf', 'taille_octets' => 10, 'statut' => $statut, 'expire_le' => $expire,
    ]);
}

// ---------------------------------------------------------------- création

it('crée ensemble le compte de connexion et la fiche, et invite à choisir un mot de passe', function (): void {
    seConnecterEn(Profil::Gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/proprietaires', [
        'nom' => 'Kouassi', 'prenoms' => 'Yao', 'email' => 'YAO@exemple.ci', 'telephone' => '05 05 05 05 05',
        'nature' => 'personne_physique',
    ])->assertCreated();

    $compte = User::firstWhere('email', 'yao@exemple.ci');
    expect($compte->profil)->toBe(Profil::Proprietaire)
        ->and($compte->statut)->toBe(StatutCompte::Actif)
        ->and($compte->telephone)->toBe('0505050505')
        ->and($reponse->json('data.mandat.mode_remuneration'))->toBe('prix_negocie')
        ->and($reponse->json('data.dossier_complet'))->toBeFalse();

    Mail::assertQueued(BienvenuePartenaireMail::class, fn ($m) => $m->hasTo('yao@exemple.ci'));
    // Aucun mot de passe ne circule : ni dans la réponse, ni dans le journal.
    expect($reponse->getContent())->not->toContain('password')
        ->and(EntreeAudit::all()->toJson())->not->toContain('$2y$');
});

it('ne crée ni compte ni fiche si la fiche est refusée', function (): void {
    seConnecterEn(Profil::Gestionnaire);

    test()->postJson('/api/v1/backoffice/proprietaires', [
        'nom' => 'SCI', 'email' => 'sci@exemple.ci', 'nature' => 'entreprise', // raison sociale manquante
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['raison_sociale']]);

    expect(User::where('email', 'sci@exemple.ci')->exists())->toBeFalse();
});

it('refuse les saisies incohérentes', function (array $saisie, string $champ): void {
    seConnecterEn(Profil::Gestionnaire);
    $base = ['nom' => 'Test', 'email' => 'test@exemple.ci', 'nature' => 'personne_physique'];

    test()->postJson('/api/v1/backoffice/proprietaires', [...$base, ...$saisie])
        ->assertStatus(422)->assertJsonStructure(['errors' => [$champ]]);
})->with([
    'assujetti à la TVA sans NCC' => [['assujetti_tva' => true], 'ncc'],
    'mode commission sans taux' => [['mode_remuneration' => 'commission'], 'taux_commission'],
    'commission au-dessus de 100 %' => [['mode_remuneration' => 'commission', 'taux_commission' => 120], 'taux_commission'],
    'mandat signé dans le futur' => [['mandat_signe_le' => '2099-01-01'], 'mandat_signe_le'],
    'mandat expiré avant sa signature' => [['mandat_signe_le' => '2026-01-10', 'mandat_expire_le' => '2026-01-01'], 'mandat_expire_le'],
    'régime inconnu' => [['regime_fiscal' => 'forfait'], 'regime_fiscal'],
]);

it('refuse un courriel déjà pris, mais laisse le propriétaire garder le sien à la modification', function (): void {
    seConnecterEn(Profil::Gestionnaire);
    User::factory()->create(['email' => 'pris@exemple.ci']);
    $p = Proprietaire::factory()->create();

    test()->postJson('/api/v1/backoffice/proprietaires', ['nom' => 'X', 'email' => 'pris@exemple.ci', 'nature' => 'personne_physique'])
        ->assertStatus(422);
    test()->putJson("/api/v1/backoffice/proprietaires/{$p->id}", ['email' => $p->utilisateur->email, 'adresse' => 'Cocody'])
        ->assertOk()->assertJsonPath('data.adresse', 'Cocody');
});

// ---------------------------------------------------------------- compte interne

it('réserve le compte « propriétaire interne » aux administrateurs, et n’en admet qu’un', function (): void {
    seConnecterEn(Profil::Gestionnaire);
    test()->postJson('/api/v1/backoffice/proprietaires', ['nom' => 'Interne', 'email' => 'i@exemple.ci', 'nature' => 'entreprise', 'raison_sociale' => 'DALAKOUN', 'interne' => true])
        ->assertForbidden();

    seConnecterEn(Profil::Administrateur);
    test()->postJson('/api/v1/backoffice/proprietaires', ['nom' => 'Interne', 'email' => 'i@exemple.ci', 'nature' => 'entreprise', 'raison_sociale' => 'DALAKOUN', 'interne' => true])
        ->assertCreated()->assertJsonPath('data.nom_affiche', 'DALAKOUN (propriétaire interne)');

    // La base refuse un second compte interne.
    expect(fn () => Proprietaire::factory()->interne()->create())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------- retenue à la source

it('applique la retenue du cahier des charges selon la nature et le régime', function (): void {
    $retenue = app(RetenueALaSource::class);

    $particulier = Proprietaire::factory()->create();
    $micro = Proprietaire::factory()->entreprise(RegimeFiscal::MicroEntreprise)->create();
    $nonRenseigne = Proprietaire::factory()->entreprise(RegimeFiscal::NonRenseigne)->create();
    $reelSansPreuve = Proprietaire::factory()->entreprise(RegimeFiscal::ReelNormal)->create();
    $reelJustifie = Proprietaire::factory()->entreprise(RegimeFiscal::ReelSimplifie)->create();
    piece($reelJustifie, TypeDePiece::Dfe);
    $interne = Proprietaire::factory()->interne()->create();

    expect($retenue->pour($particulier)['taux'])->toBe(7.5)
        ->and($retenue->pour($micro)['taux'])->toBe(2.0)
        ->and($retenue->pour($nonRenseigne)['taux'])->toBe(2.0)
        // Un régime réel seulement DÉCLARÉ ne dispense de rien.
        ->and($retenue->pour($reelSansPreuve)['taux'])->toBe(2.0)
        ->and($retenue->pour($reelSansPreuve)['motif'])->toContain('non justifié')
        ->and($retenue->pour($reelJustifie->refresh())['taux'])->toBe(0.0)
        ->and($retenue->pour($interne)['taux'])->toBe(0.0);
});

it('ne tient pas compte d’une DFE en attente, refusée ou périmée', function (StatutDePiece $statut, ?string $expire): void {
    $p = Proprietaire::factory()->entreprise(RegimeFiscal::ReelNormal)->create();
    piece($p, TypeDePiece::Dfe, $statut, $expire);

    expect(app(RetenueALaSource::class)->pour($p->refresh())['taux'])->toBe(2.0);
})->with([
    'en attente' => [StatutDePiece::EnAttente, null],
    'refusée' => [StatutDePiece::Refusee, null],
    'validée mais périmée' => [StatutDePiece::Validee, '2020-01-01'],
]);

// ---------------------------------------------------------------- dossier

it('liste ce qui manque au dossier, puis le déclare complet', function (): void {
    $p = Proprietaire::factory()->create();

    expect($p->elementsManquants())->toBe(['Pièce d’identité', 'Titre de propriété ou bail', 'RIB', 'Mandat de gestion signé', 'Régime fiscal']);

    $p->update(['regime_fiscal' => RegimeFiscal::Aucun, 'mandat_signe_le' => '2026-09-01']);
    foreach ([TypeDePiece::PieceIdentite, TypeDePiece::Bail, TypeDePiece::Rib, TypeDePiece::Mandat] as $type) {
        piece($p, $type);
    }

    expect($p->refresh()->dossierComplet())->toBeTrue();
});

it('ne demande rien au compte interne de l’entreprise', function (): void {
    expect(Proprietaire::factory()->interne()->create()->dossierComplet())->toBeTrue();
});

// ---------------------------------------------------------------- pièces

it('dépose une pièce chiffrée sur le disque, en attente de vérification', function (): void {
    seConnecterEn(Profil::Gestionnaire);
    $p = Proprietaire::factory()->create();
    $fichier = UploadedFile::fake()->createWithContent('cni.pdf', '%PDF-1.4 NUMERO-CNI-C0012345678');

    $id = test()->post("/api/v1/backoffice/proprietaires/{$p->id}/pieces", ['type' => 'piece_identite', 'fichier' => $fichier], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.statut', 'en_attente')->assertJsonMissingPath('data.chemin')->json('data.id');

    $piece = PieceJustificative::findOrFail($id);
    $surLeDisque = Storage::disk('local')->get($piece->chemin);
    expect($surLeDisque)->not->toContain('NUMERO-CNI')
        ->and(app(PiecesJustificatives::class)->contenu($piece))->toContain('NUMERO-CNI-C0012345678');
});

it('refuse un type de fichier ou de pièce non prévu', function (): void {
    seConnecterEn(Profil::Gestionnaire);
    $p = Proprietaire::factory()->create();

    test()->post("/api/v1/backoffice/proprietaires/{$p->id}/pieces", ['type' => 'piece_identite', 'fichier' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);
    test()->post("/api/v1/backoffice/proprietaires/{$p->id}/pieces", ['type' => 'permis_de_chasse', 'fichier' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
});

it('rend le fichier en clair au téléchargement, et trace la consultation', function (): void {
    $lecteur = seConnecterEn(Profil::Gestionnaire);
    $p = Proprietaire::factory()->create();
    $piece = app(PiecesJustificatives::class)->deposer($p, TypeDePiece::Rib, UploadedFile::fake()->createWithContent('rib.pdf', '%PDF IBAN-CI93'), null, $lecteur);

    $reponse = test()->get("/api/v1/backoffice/proprietaires/{$p->id}/pieces/{$piece->id}/telecharger")->assertOk();

    expect($reponse->getContent())->toContain('IBAN-CI93')
        ->and($reponse->headers->get('Cache-Control'))->toContain('no-store')
        ->and(EntreeAudit::where('action', 'consultation_piece')->where('user_id', $lecteur->id)->count())->toBe(1);
});

it('réserve la vérification aux administrateurs, jamais sur leur propre dépôt, et exige un motif de refus', function (): void {
    $gestionnaire = seConnecterEn(Profil::Gestionnaire);
    $p = Proprietaire::factory()->create();
    $piece = app(PiecesJustificatives::class)->deposer($p, TypeDePiece::Rib, UploadedFile::fake()->create('rib.pdf', 10, 'application/pdf'), null, $gestionnaire);
    $adresse = "/api/v1/backoffice/proprietaires/{$p->id}/pieces/{$piece->id}/decision";

    test()->putJson($adresse, ['decision' => 'valider'])->assertForbidden();

    $admin = seConnecterEn(Profil::Administrateur);
    test()->putJson($adresse, ['decision' => 'refuser'])->assertStatus(422)->assertJsonStructure(['errors' => ['motif']]);
    test()->putJson($adresse, ['decision' => 'refuser', 'motif' => 'Document illisible'])->assertOk()->assertJsonPath('data.statut', 'refusee');
    test()->putJson($adresse, ['decision' => 'valider'])->assertOk()->assertJsonPath('data.valable', true);

    // Un administrateur ne valide pas la pièce qu'il a lui-même déposée.
    $sienne = app(PiecesJustificatives::class)->deposer($p, TypeDePiece::Mandat, UploadedFile::fake()->create('mandat.pdf', 10, 'application/pdf'), null, $admin);
    test()->putJson("/api/v1/backoffice/proprietaires/{$p->id}/pieces/{$sienne->id}/decision", ['decision' => 'valider'])
        ->assertForbidden()->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');
});

it('ne trouve une pièce que dans le dossier de son propriétaire', function (): void {
    seConnecterEn(Profil::Gestionnaire);
    $piece = piece(Proprietaire::factory()->create(), TypeDePiece::Rib);
    $autre = Proprietaire::factory()->create();

    test()->get("/api/v1/backoffice/proprietaires/{$autre->id}/pieces/{$piece->id}/telecharger")->assertNotFound();
});

it('ferme les propriétaires et leurs pièces aux profils hors exploitation', function (Profil $profil): void {
    $p = Proprietaire::factory()->create();
    seConnecterEn($profil);

    test()->getJson('/api/v1/backoffice/proprietaires')->assertForbidden();
    test()->getJson("/api/v1/backoffice/proprietaires/{$p->id}/pieces")->assertForbidden();
})->with([Profil::Proprietaire, Profil::Client, Profil::Gouvernante]);

it('cherche dans la liste et met le compte interne en tête', function (): void {
    seConnecterEn(Profil::Administrateur);
    Proprietaire::factory()->create(['user_id' => User::factory()->profil(Profil::Proprietaire)->create(['nom' => 'Bamba'])->id]);
    Proprietaire::factory()->interne()->create();

    $tous = test()->getJson('/api/v1/backoffice/proprietaires')->assertOk()->json('data.elements');
    expect($tous[0]['interne'])->toBeTrue();

    $recherche = test()->getJson('/api/v1/backoffice/proprietaires?recherche=bamb')->assertOk()->json('data.elements');
    expect($recherche)->toHaveCount(1)->and($recherche[0]['compte']['nom'])->toBe('Bamba');
});

// ---------------------------------------------------------------- export (CdC § 6.8)

it('exporte la liste des propriétaires en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    seConnecterEn(Profil::Administrateur);
    Proprietaire::factory()->create(['user_id' => User::factory()->profil(Profil::Proprietaire)->create(['nom' => 'Bamba'])->id]);

    $reponse = test()->getJson('/api/v1/backoffice/proprietaires/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /proprietaires/export avant /proprietaires/{proprietaire} : "export" n’est jamais pris pour un identifiant', function (): void {
    seConnecterEn(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/proprietaires/export?format=xlsx')->assertOk();
});
