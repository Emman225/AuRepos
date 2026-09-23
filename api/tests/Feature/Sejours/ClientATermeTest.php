<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ComptesATerme;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->logement = Logement::factory()->create();
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $this->administrateur = User::factory()->profil(Profil::Administrateur)->create();
});

/** Dépose et valide directement les trois pièces obligatoires (CdC § 5.3), sans passer par HTTP. */
function completerLeDossier(Client $client): void
{
    foreach ([TypeDePiece::Rccm, TypeDePiece::Bilan, TypeDePiece::PieceIdentite] as $type) {
        PieceJustificative::create([
            'titulaire_type' => $client->getMorphClass(), 'titulaire_id' => $client->id,
            'type' => $type, 'chemin' => 'pieces/test-'.$type->value.'.chiffre', 'nom_original' => $type->value.'.pdf',
            'mime' => 'application/pdf', 'taille_octets' => 100, 'statut' => StatutDePiece::Validee,
            'deposee_par' => test()->administrateur->id, 'verifiee_par' => test()->administrateur->id, 'verifiee_le' => now(),
        ]);
    }
}

/** Mène un règlement au bout du circuit avec deux administrateurs distincts. */
function finaliserUnReglement(Reglement $reglement, User $a1, User $a2): void
{
    $caisse = app(Caisse::class);
    $caisse->valider($reglement, $a1);
    $caisse->joindreLaPreuve($reglement->refresh(), $a2, UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'));
    $caisse->finaliser($reglement->refresh(), $a2);
}

// ---------------------------------------------------------------- demande

it('refuse la demande de compte à terme à un client particulier (b2c)', function (): void {
    $client = User::factory()->create();

    expect(fn () => app(ComptesATerme::class)->demander($client))
        ->toThrow(ErreurMetier::class, 'organisations');
});

it('accepte la demande d’une organisation et la place en attente', function (): void {
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b', 'raison_sociale' => 'ACME SARL']);

    $client = app(ComptesATerme::class)->demander($utilisateur);

    expect($client->statut_a_terme)->toBe(StatutDemandeATerme::EnAttente)
        ->and($client->demande_a_terme_le)->not->toBeNull();
});

it('refuse une seconde demande tant que la première est en instruction', function (): void {
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b']);
    app(ComptesATerme::class)->demander($utilisateur);

    expect(fn () => app(ComptesATerme::class)->demander($utilisateur))
        ->toThrow(ErreurMetier::class, 'en cours d’instruction');
});

// ---------------------------------------------------------------- décision du dossier

it('refuse d’accepter un dossier incomplet (pièces manquantes)', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);

    expect(fn () => app(ComptesATerme::class)->accepter($client, 500000, $this->administrateur))
        ->toThrow(ErreurMetier::class, 'Dossier incomplet');
});

it('accepte un dossier complet et fixe le plafond de crédit', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);
    completerLeDossier($client);

    $client = app(ComptesATerme::class)->accepter($client->refresh(), 500000, $this->administrateur);

    expect($client->statut_a_terme)->toBe(StatutDemandeATerme::Acceptee)
        ->and($client->plafond_credit)->toBe(500000)
        ->and($client->a_terme_traite_par)->toBe($this->administrateur->id)
        ->and($client->estATerme())->toBeTrue();
});

it('refuse un dossier avec motif', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);

    $client = app(ComptesATerme::class)->refuser($client, 'RCCM expiré, à renouveler.', $this->administrateur);

    expect($client->statut_a_terme)->toBe(StatutDemandeATerme::Refusee)
        ->and($client->a_terme_motif_refus)->toBe('RCCM expiré, à renouveler.');
});

// ---------------------------------------------------------------- réservation à terme

it('refuse une réservation à terme tant que le client n’a pas de ligne de crédit acceptée', function (): void {
    $utilisateur = User::factory()->create();
    test()->withToken(auth('api')->login($utilisateur));

    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'a_terme', 'bon_de_commande' => 'BC-TEST-001',
    ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'client_non_a_terme');
});

it('accepte une réservation à terme dans la limite du plafond, sans expiration', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);
    completerLeDossier($client);
    app(ComptesATerme::class)->accepter($client->refresh(), 200000, $this->administrateur);

    test()->withToken(auth('api')->login($utilisateur));
    $reponse = test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'a_terme', 'bon_de_commande' => 'BC-TEST-001',
    ])->assertCreated();

    $sejour = Sejour::where('reference', $reponse->json('data.reference'))->firstOrFail();
    expect($sejour->mode_reglement)->toBe('a_terme')->and($sejour->expire_le)->toBeNull();
});

it('refuse une réservation à terme qui dépasserait le plafond, avec le détail du calcul', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);
    completerLeDossier($client);
    // Plafond volontairement bas : le net à payer d'un séjour de trois nuits le dépasse largement.
    app(ComptesATerme::class)->accepter($client->refresh(), 10000, $this->administrateur);

    test()->withToken(auth('api')->login($utilisateur));
    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'a_terme', 'bon_de_commande' => 'BC-TEST-001',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'plafond_de_credit_depasse');
});

it('plafond à zéro : aucune limite', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    $client->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);
    completerLeDossier($client);
    app(ComptesATerme::class)->accepter($client->refresh(), 0, $this->administrateur);

    test()->withToken(auth('api')->login($utilisateur));
    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $this->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'a_terme', 'bon_de_commande' => 'BC-TEST-001',
    ])->assertCreated();
});

// ---------------------------------------------------------------- encours

it('calcule l’encours comme la somme des restes dus, réglements effectués déduits', function (): void {
    $utilisateur = User::factory()->create();
    $client = Client::de($utilisateur);
    [$a1, $a2] = [User::factory()->profil(Profil::Administrateur)->create(), User::factory()->profil(Profil::Administrateur)->create()];
    $caissier = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => Agence::factory()->create()->id]);

    $s1 = Sejour::factory()->create(['client_id' => $utilisateur->id, 'net_a_payer' => 100000, 'acompte_exige' => 0, 'etat' => EtatDuSejour::Confirme]);
    $s2 = Sejour::factory()->create(['client_id' => $utilisateur->id, 'net_a_payer' => 50000, 'acompte_exige' => 0, 'etat' => EtatDuSejour::Confirme]);
    // Un séjour annulé ne doit JAMAIS compter dans l'encours.
    Sejour::factory()->create(['client_id' => $utilisateur->id, 'net_a_payer' => 999999, 'acompte_exige' => 0, 'etat' => EtatDuSejour::Annule]);

    expect(app(ComptesATerme::class)->encours($client))->toBe(150000);

    // Un règlement EFFECTUÉ sur s1 réduit l'encours d'autant.
    $reglement = app(Caisse::class)->saisirUnEncaissement($caissier, $utilisateur, [$s1->id], 60000, ModeDeReglement::Especes, 'Acompte reçu');
    finaliserUnReglement($reglement, $a1, $a2);

    expect(app(ComptesATerme::class)->encours($client))->toBe(90000); // (100000-60000) + 50000

    // Un règlement seulement SAISI (pas encore effectué) réserve quand même sa place.
    app(Caisse::class)->saisirUnEncaissement($caissier, $utilisateur, [$s2->id], 20000, ModeDeReglement::Especes, 'Second acompte, en attente');
    expect(app(ComptesATerme::class)->encours($client))->toBe(70000); // 40000 + 30000
});
