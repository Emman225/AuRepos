<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->profil(Profil::Administrateur)->create();
    $this->tresorier = User::factory()->profil(Profil::Administrateur)->create();

    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);
});

function connecteReduction(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

function designerLeTresorier(User $tresorier): void
{
    app(Parametres::class)->enregistrer('gestionnaires', ['validant_2_id' => $tresorier->id], test()->admin);
}

// ---------------------------------------------------------------- proposition

it('refuse toute proposition tant qu’aucun trésorier n’est désigné', function (): void {
    connecteReduction($this->admin);

    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'tresorier_non_designe');
});

it('propose une réduction une fois le trésorier désigné, sans rien changer avant confirmation', function (): void {
    designerLeTresorier($this->tresorier);
    connecteReduction($this->admin);
    $netAvant = $this->sejour->net_a_payer;

    $reponse = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])
        ->assertCreated();

    expect($reponse->json('data.champ'))->toBe('reduction_pourcentage')
        ->and($this->sejour->refresh()->net_a_payer)->toBe($netAvant); // rien n'a bougé
});

it('ferme la proposition aux non-administrateurs', function (): void {
    designerLeTresorier($this->tresorier);
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    // Rattaché à la résidence du séjour : c'est bien le PROFIL qui doit refuser, pas le cloisonnement.
    $gestionnaire->residences()->attach($this->sejour->logement->residence_id);
    connecteReduction($gestionnaire);

    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])
        ->assertForbidden();
});

// ---------------------------------------------------------------- confirmation : LE trésorier, pas un autre administrateur

it('refuse qu’un administrateur QUELCONQUE confirme : seul LE trésorier désigné le peut', function (): void {
    designerLeTresorier($this->tresorier);
    connecteReduction($this->admin);
    $id = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])->json('data.id');

    // Un troisième administrateur, différent du proposant ET du trésorier : passerait la double
    // validation générique, mais pas cette règle-ci.
    connecteReduction(User::factory()->profil(Profil::Administrateur)->create());
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertStatus(403)->assertJsonPath('errors.code.0', 'tresorier_requis');

    expect($this->sejour->refresh()->reduction_pourcentage)->toBeNull();
});

it('confirme et recalcule le séjour quand LE trésorier désigné valide', function (): void {
    designerLeTresorier($this->tresorier);
    connecteReduction($this->admin);
    $id = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])->json('data.id');

    connecteReduction($this->tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    $sejour = $this->sejour->refresh();
    // 3 nuits à 30 000 F = 90 000 F HT ; 10 % = 9 000 F ; TVA 18 %, TDT 3 % en cascade sur le reste.
    expect($sejour->reduction_pourcentage)->toBe(10.0)
        ->and($sejour->devis['remise_ht'])->toBe(9000)
        ->and($sejour->net_a_payer)->toBe(98447)
        ->and($sejour->net_a_payer)->toBeLessThan(109386); // le prix d'origine, sans réduction
});

it('ne laisse pas un simple administrateur confirmer sa PROPRE proposition, même s’il devient trésorier après coup', function (): void {
    designerLeTresorier($this->admin); // l'auteur de la proposition est lui-même désigné trésorier
    connecteReduction($this->admin);
    $id = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])->json('data.id');

    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertStatus(403)->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');
});

// ---------------------------------------------------------------- plancher et réductions déjà accordées

it('plafonne la réduction au plancher du minimum à payer, sans jamais rogner les réductions déjà accordées', function (): void {
    designerLeTresorier($this->tresorier);
    app(Parametres::class)->enregistrer('general', ['minimum_a_payer' => 100000], $this->admin);

    connecteReduction($this->admin);
    $id = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 90, 'motif' => 'Très fort geste'])->json('data.id');

    connecteReduction($this->tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    // Le net ne descend jamais sous le minimum paramétré (CdC § 4), même pour un « très fort geste ».
    expect($this->sejour->refresh()->net_a_payer)->toBeGreaterThanOrEqual(100000);
});

it('recalcule l’acompte exigé dans la même proportion que le nouveau net à payer', function (): void {
    designerLeTresorier($this->tresorier);
    $acompteAvant = $this->sejour->acompte_exige;
    $netAvant = $this->sejour->net_a_payer;

    connecteReduction($this->admin);
    $id = test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10, 'motif' => 'Geste commercial'])->json('data.id');
    connecteReduction($this->tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->acompte_exige)->toBe((int) round($acompteAvant * $sejour->net_a_payer / $netAvant));
});

// ---------------------------------------------------------------- refus et validation de base

it('refuse un pourcentage hors bornes', function (): void {
    designerLeTresorier($this->tresorier);
    connecteReduction($this->admin);

    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 150, 'motif' => 'Geste commercial'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['pourcentage']]);
});

it('exige un motif', function (): void {
    designerLeTresorier($this->tresorier);
    connecteReduction($this->admin);

    test()->postJson("/api/v1/backoffice/sejours/{$this->sejour->id}/reduction", ['pourcentage' => 10])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['motif']]);
});
