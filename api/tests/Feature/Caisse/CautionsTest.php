<?php

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Cautions;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $this->agence->id]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    // Un décaissement (restitution) veut TROIS administrateurs distincts (saisie, validation, preuve/finalisation) :
    // admin3 saisit, pour laisser admin1/admin2 dérouler le reste du circuit dans `terminerLeCircuitDeCaution`.
    [$this->admin1, $this->admin2, $this->admin3] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 100000, 'caution' => 50000]);
});

function connecteAuGuichetCautions(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Déroule le circuit de preuve d'un règlement du guichet Cautions jusqu'à « effectué ». */
function terminerLeCircuitDeCaution(int $reglementId): void
{
    connecteAuGuichetCautions(test()->admin1);
    test()->putJson("/api/v1/backoffice/caisse/reglements/{$reglementId}/validation")->assertOk();
    connecteAuGuichetCautions(test()->admin2);
    test()->post("/api/v1/backoffice/caisse/reglements/{$reglementId}/preuve", [
        'justificatif' => UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertOk();
    test()->putJson("/api/v1/backoffice/caisse/reglements/{$reglementId}/finalisation")->assertOk();
}

function deposerLaCautionDuSejour(Sejour $sejour): int
{
    connecteAuGuichetCautions(test()->caissier);
    $id = test()->postJson("/api/v1/backoffice/caisse/cautions/{$sejour->id}/depot", [
        'montant' => $sejour->caution, 'mode' => 'especes', 'notes' => 'Caution déposée au guichet',
    ])->assertCreated()->json('data.id');
    terminerLeCircuitDeCaution($id);

    return $id;
}

it('dépose la caution d’un séjour : circuit de preuve complet et reçu RK', function (): void {
    connecteAuGuichetCautions($this->caissier);
    $id = deposerLaCautionDuSejour($this->sejour);

    $reglement = Reglement::findOrFail($id);
    expect($reglement->etat)->toBe(EtatDuReglement::Effectue)
        ->and($reglement->guichet)->toBe(Guichet::Cautions)
        ->and($reglement->sejour_id)->toBe($this->sejour->id)
        ->and($reglement->numero_recu)->toStartWith('RK-')
        ->and($reglement->imputations()->count())->toBe(0); // jamais une imputation : la caution n'entre pas dans le reste dû

    expect(app(Cautions::class)->estEncaissee($this->sejour))->toBeTrue();

    // Un second dépôt sur le même séjour est refusé.
    connecteAuGuichetCautions($this->caissier);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/depot", [
        'montant' => $this->sejour->caution, 'mode' => 'especes', 'notes' => 'Second dépôt, refusé',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'caution_deja_encaissee');
});

it('refuse un dépôt dont le montant ne correspond pas exactement à la caution du séjour', function (): void {
    connecteAuGuichetCautions($this->caissier);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/depot", [
        'montant' => 40000, 'mode' => 'especes', 'notes' => 'Montant incomplet',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'montant_different_de_la_caution');
});

it('restitue intégralement la caution quand rien n’est retenu : décaissement, circuit de preuve, reçu RK-R', function (): void {
    deposerLaCautionDuSejour($this->sejour);

    // Saisie par un TROISIÈME administrateur : la validation et la preuve, ensuite, en veulent deux autres.
    connecteAuGuichetCautions($this->admin3);
    $id = test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/restitution", [
        'mode' => 'especes', 'notes' => 'Rien à retenir, restitution intégrale',
    ])->assertCreated()->json('data.id');
    terminerLeCircuitDeCaution($id);

    $reglement = Reglement::findOrFail($id);
    expect($reglement->sens)->toBe('decaissement')
        ->and($reglement->guichet)->toBe(Guichet::Cautions)
        ->and($reglement->montant)->toBe(50000)
        ->and($reglement->sejour_id)->toBe($this->sejour->id)
        ->and($reglement->numero_recu)->toStartWith('RK-R-');

    $solde = app(Cautions::class)->soldeDe($this->sejour->refresh());
    expect($solde)->toBe(['totale' => 50000, 'deposee' => 50000, 'retenue' => 0, 'restituee' => 50000, 'detenue' => 0]);
});

it('refuse de restituer plus que la part encore détenue', function (): void {
    deposerLaCautionDuSejour($this->sejour);

    connecteAuGuichetCautions($this->admin1);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/restitution", [
        'montant' => 60000, 'mode' => 'especes', 'notes' => 'Trop demandé',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'restitution_superieure_au_detenu');
});

it('retient une partie de la caution avec un motif obligatoire, des justificatifs, et une facture « frais de dégradation »', function (): void {
    deposerLaCautionDuSejour($this->sejour);

    connecteAuGuichetCautions($this->admin1);
    // Sans motif : refusé par la validation de la requête, avant même le service.
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/retenue", ['montant' => 15000])
        ->assertStatus(422)->assertJsonValidationErrors('motif');

    $reponse = test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/retenue", [
        'montant' => 15000, 'motif' => 'Mur du salon endommagé, réparation à charge du client.',
        'justificatifs' => [UploadedFile::fake()->image('degat.jpg', 800, 600)->size(300)],
    ])->assertCreated();

    $reponse->assertJsonPath('data.montant', 15000)->assertJsonPath('data.justificatifs', 1);
    $factureNumero = $reponse->json('data.facture.numero');
    expect($factureNumero)->toStartWith('FRC-');

    $facture = Facture::where('numero', $factureNumero)->sole();
    expect($facture->type)->toBe(TypeDeFacture::FraisCaution)
        ->and($facture->sejour_id)->toBe($this->sejour->id)
        ->and($facture->montant_ttc)->toBe(15000)
        ->and($facture->lignes)->toHaveCount(1)
        ->and($facture->lignes[0]['description'])->toContain('Frais de dégradation / retard');

    expect(DB::table('pieces_justificatives')->where('titulaire_type', \App\Domain\Caisse\Models\RetenueDeCaution::class)->count())->toBe(1);

    $solde = app(Cautions::class)->soldeDe($this->sejour->refresh());
    expect($solde)->toBe(['totale' => 50000, 'deposee' => 50000, 'retenue' => 15000, 'restituee' => 0, 'detenue' => 35000]);
});

it('refuse une retenue supérieure à la part encore détenue', function (): void {
    deposerLaCautionDuSejour($this->sejour);

    connecteAuGuichetCautions($this->admin1);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/retenue", [
        'montant' => 90000, 'motif' => 'Dégât disproportionné, jamais accepté',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'retenue_superieure_au_detenu');
});

it('refuse une retenue ou une restitution tant que la caution n’est pas encaissée', function (): void {
    connecteAuGuichetCautions($this->admin1);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/retenue", ['montant' => 1000, 'motif' => 'Motif quelconque'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'caution_non_encaissee');
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/restitution", ['mode' => 'especes', 'notes' => 'Rien à restituer'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'caution_non_encaissee');
});

it('prouve, sur l’état des cautions, l’égalité retenue + restituée + détenue = caution totale (P2-CAU-03)', function (): void {
    // Un premier séjour : caution partiellement retenue, le reste restitué (détenue = 0).
    deposerLaCautionDuSejour($this->sejour);
    connecteAuGuichetCautions($this->admin1);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/retenue", [
        'montant' => 20000, 'motif' => 'Literie tachée, nettoyage professionnel.',
    ])->assertCreated();

    connecteAuGuichetCautions($this->admin3); // troisième administrateur : cf. remarque plus haut sur la saisie du décaissement
    $idRestitution = test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/restitution", [
        'mode' => 'especes', 'notes' => 'Reste de la caution restitué',
    ])->assertCreated()->json('data.id');
    terminerLeCircuitDeCaution($idRestitution);

    // Un second séjour : caution intégralement détenue (ni retenue ni restituée).
    $second = Sejour::factory()->create(['client_id' => $this->client->id, 'net_a_payer' => 80000, 'caution' => 30000]);
    deposerLaCautionDuSejour($second);

    connecteAuGuichetCautions($this->caissier);
    $reponse = test()->getJson('/api/v1/backoffice/caisse/cautions/etat?du='.now()->subDay()->toDateString().'&au='.now()->addDay()->toDateString())
        ->assertOk();

    $reponse->assertJsonPath('data.nombre_de_sejours', 2)
        ->assertJsonPath('data.caution_totale', 80000) // 50000 + 30000
        ->assertJsonPath('data.retenue', 20000)
        ->assertJsonPath('data.restituee', 30000) // 50000 - 20000
        ->assertJsonPath('data.detenue', 30000) // le second séjour, intact
        ->assertJsonPath('data.identite_verifiee', true);

    expect($reponse->json('data.retenue') + $reponse->json('data.restituee') + $reponse->json('data.detenue'))
        ->toBe($reponse->json('data.caution_totale'));
});

it('ferme le guichet Cautions aux profils sans agence, et la restitution/la retenue aux non-administrateurs', function (): void {
    deposerLaCautionDuSejour($this->sejour);

    $sansAgence = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => null]);
    connecteAuGuichetCautions($sansAgence);
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/depot", ['montant' => 50000, 'mode' => 'especes', 'notes' => 'Sans agence, refusé'])
        ->assertForbidden();

    connecteAuGuichetCautions($this->caissier); // gestionnaire, pas administrateur
    test()->postJson("/api/v1/backoffice/caisse/cautions/{$this->sejour->id}/restitution", ['mode' => 'especes', 'notes' => 'Gestionnaire, refusé'])
        ->assertForbidden();
});
