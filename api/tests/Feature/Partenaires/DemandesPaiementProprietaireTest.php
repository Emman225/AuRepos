<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Models\DemandePaiementProprietaire;
use App\Domain\Partenaires\Services\DemandesPaiementProprietaire;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\BordereauPaiementProprietaireMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

function agirEnAvecAgence(Profil $profil, ?Agence $agence = null): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create(['agence_id' => ($agence ?? Agence::factory()->create())->id]);
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

/** Un propriétaire avec un solde net dû, prêt à recevoir un paiement. */
function proprietaireAvecSolde(int $brutParBon = 45000): Proprietaire
{
    $proprietaire = Proprietaire::factory()->create(['regime_fiscal' => RegimeFiscal::Aucun]); // personne physique, 7,5 %
    $logement = Logement::factory()->create(['residence_id' => Residence::factory()->create(['proprietaire_id' => $proprietaire->id])]);
    $sejour = Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Parti, 'arrivee' => '2026-11-01', 'depart' => '2026-11-04',
        'client_id' => User::factory()->create()->id,
    ]);
    BonDeMiseADisposition::create([
        'sejour_id' => $sejour->id, 'proprietaire_id' => $proprietaire->id, 'nuitees' => 3,
        'prix_proprietaire_par_nuit' => $brutParBon / 3, 'etat' => 'valide', 'valide_automatiquement' => true, 'valide_le' => now(),
    ]);

    return $proprietaire;
}

// ---------------------------------------------------------------- plafond au solde net (CdC § 8.7)

it('plafonne la demande au solde net — jamais au brut', function (): void {
    $proprietaire = proprietaireAvecSolde(45000); // net = 45000 − 7,5 % = 41 625

    $demandes = app(DemandesPaiementProprietaire::class);
    expect(fn () => $demandes->demander($proprietaire, 42000, User::factory()->profil(Profil::Administrateur)->create()))
        ->toThrow(ErreurMetier::class);

    $demande = $demandes->demander($proprietaire, 41625, User::factory()->profil(Profil::Administrateur)->create());
    expect($demande->montant)->toBe(41625)
        ->and($demande->retenue_taux)->toBe(7.5)
        // brut équivalent : 41625 / (1 − 7,5 %) = 45 000 (arrondi).
        ->and($demande->montant_brut_equivalent)->toBe(45000);
});

it('cumule les demandes déjà en attente dans le plafond', function (): void {
    $proprietaire = proprietaireAvecSolde(45000); // net ≈ 41 625
    $demandes = app(DemandesPaiementProprietaire::class);
    $auteur = User::factory()->profil(Profil::Administrateur)->create();

    $demandes->demander($proprietaire, 30000, $auteur);
    expect(fn () => $demandes->demander($proprietaire, 15000, $auteur))->toThrow(ErreurMetier::class);
});

it('bloque toute demande pour un propriétaire tiers sans régime fiscal renseigné', function (): void {
    $proprietaire = Proprietaire::factory()->create(); // régime non renseigné
    $logement = Logement::factory()->create(['residence_id' => Residence::factory()->create(['proprietaire_id' => $proprietaire->id])]);

    expect(fn () => app(DemandesPaiementProprietaire::class)->demander($proprietaire, 1000, User::factory()->profil(Profil::Administrateur)->create()))
        ->toThrow(ErreurMetier::class);
});

// ---------------------------------------------------------------- décaissement : circuit de preuve de la caisse

it('décaisse via le même circuit de preuve que la caisse, et envoie le bordereau à la finalisation', function (): void {
    $proprietaire = proprietaireAvecSolde(45000);
    $demandes = app(DemandesPaiementProprietaire::class);
    $demande = $demandes->demander($proprietaire, 41625, User::factory()->profil(Profil::Administrateur)->create());

    $agence = Agence::factory()->create();
    $auteur = agirEnAvecAgence(Profil::Administrateur, $agence);
    $reglement = $demandes->decaisser($demande, $auteur, ModeDeReglement::Especes, 'Paiement mensuel');

    expect($demande->refresh()->etat)->toBe('decaissee')->and($demande->reglement_id)->toBe($reglement->id);
    Mail::assertNotQueued(BordereauPaiementProprietaireMail::class); // pas avant finalisation

    $validateur = User::factory()->profil(Profil::Administrateur)->create(['agence_id' => $agence->id]);
    $troisieme = User::factory()->profil(Profil::Administrateur)->create(['agence_id' => $agence->id]);
    $caisse = app(Caisse::class);
    $caisse->valider($reglement, $validateur);
    $caisse->joindreLaPreuve($reglement->refresh(), $troisieme, UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'));
    $caisse->finaliser($reglement->refresh(), $troisieme);

    Mail::assertQueued(BordereauPaiementProprietaireMail::class, fn ($m) => $m->reglement->is($reglement->fresh()));
});

it('rejette une demande, avec motif, et ne la décaisse plus', function (): void {
    $proprietaire = proprietaireAvecSolde(45000);
    $demandes = app(DemandesPaiementProprietaire::class);
    $demande = $demandes->demander($proprietaire, 20000, User::factory()->profil(Profil::Administrateur)->create());

    $demandes->rejeter($demande, User::factory()->profil(Profil::Administrateur)->create(), 'Dossier propriétaire incomplet');

    expect($demande->refresh()->etat)->toBe('rejetee')->and($demande->motif_rejet)->toBe('Dossier propriétaire incomplet');
    expect(fn () => $demandes->decaisser($demande, agirEnAvecAgence(Profil::Administrateur), ModeDeReglement::Especes, 'x'))
        ->toThrow(ErreurMetier::class);
});

// ---------------------------------------------------------------- API propriétaire

it('laisse le propriétaire demander un paiement depuis son espace, plafonné à son solde', function (): void {
    $proprietaire = proprietaireAvecSolde(45000);
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($proprietaire->utilisateur));

    test()->postJson('/api/v1/proprietaire/demandes-paiement', ['montant' => 100000])->assertStatus(422);
    $reponse = test()->postJson('/api/v1/proprietaire/demandes-paiement', ['montant' => 41625])->assertCreated();

    expect(DemandePaiementProprietaire::sole()->proprietaire_id)->toBe($proprietaire->id)
        ->and($reponse->json('data.montant'))->toBe(41625);
});
