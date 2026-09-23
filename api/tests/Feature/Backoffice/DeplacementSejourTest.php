<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    $this->residence = Residence::factory()->create();
    $this->gestionnaire->residences()->attach($this->residence->id);

    $this->typeA = TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3]);
    $this->typeB = TypeLogement::firstOrCreate(['code' => 'f4'], ['nom' => 'Appartement 4 pièces', 'nombre_pieces' => 4]);

    $this->logementSource = Logement::factory()->create(['residence_id' => $this->residence->id, 'type_logement_id' => $this->typeA->id]);
    $this->logementSource->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();

    $this->client = User::factory()->create();
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $this->logementSource->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    test()->withToken(auth('api')->login($this->gestionnaire));
});

it('déplace un séjour vers un logement du même type, revérifie le calendrier et journalise le motif', function (): void {
    $destination = Logement::factory()->create(['residence_id' => $this->residence->id, 'type_logement_id' => $this->typeA->id]);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $destination->id,
        'motif' => 'Panne de climatisation dans le logement initial',
    ])->assertOk();

    $sejour = $this->sejour->refresh();
    expect($sejour->logement_id)->toBe($destination->id);

    // La contrainte d'exclusion a bien suivi le séjour vers son nouveau logement.
    expect(DB::table('occupations')->where('sejour_id', $sejour->id)->value('logement_id'))->toBe($destination->id);

    $entree = EntreeAudit::query()->where('action', 'sejour_deplace')->latest('id')->first();
    expect($entree)->not->toBeNull();
    expect($entree->recit)->toContain('Panne de climatisation');
    expect($entree->apres['motif'])->toBe('Panne de climatisation dans le logement initial');
    expect($entree->apres['logement_id'])->toBe($destination->id);
    expect($entree->avant['logement_id'])->toBe($this->logementSource->id);
});

it('refuse un déplacement vers un logement d’un autre type', function (): void {
    $autreType = Logement::factory()->create(['residence_id' => $this->residence->id, 'type_logement_id' => $this->typeB->id]);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $autreType->id,
        'motif' => 'Test de déplacement refusé',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'type_logement_different');
});

it('refuse un déplacement si le logement de destination est déjà occupé sur ces dates (contrainte d’exclusion)', function (): void {
    $destination = Logement::factory()->create(['residence_id' => $this->residence->id, 'type_logement_id' => $this->typeA->id]);
    $destination->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();

    $autreClient = User::factory()->create();
    app(ReservationDeSejour::class)->reserver($autreClient, $destination->refresh(), [
        'arrivee' => '2026-11-11', 'depart' => '2026-11-14', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $destination->id,
        'motif' => 'Chevauche un autre séjour',
    ])->assertStatus(409)->assertJsonPath('errors.code.0', 'dates_indisponibles');

    // Le déplacement n'a pas eu lieu : le séjour est resté dans son logement d'origine.
    expect($this->sejour->refresh()->logement_id)->toBe($this->logementSource->id);
});

it('exige un motif', function (): void {
    $destination = Logement::factory()->create(['residence_id' => $this->residence->id, 'type_logement_id' => $this->typeA->id]);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $destination->id,
    ])->assertStatus(422)->assertJsonValidationErrors('motif');
});

it('refuse un déplacement vers le même logement', function (): void {
    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $this->logementSource->id,
        'motif' => 'Sans effet',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'logement_inchange');
});

it('un gestionnaire ne peut pas déplacer un séjour vers un logement hors de son périmètre (404)', function (): void {
    $autreResidence = Residence::factory()->create();
    $destinationHorsPerimetre = Logement::factory()->create(['residence_id' => $autreResidence->id, 'type_logement_id' => $this->typeA->id]);

    test()->putJson("/api/v1/backoffice/sejours/{$this->sejour->id}/logement", [
        'logement_id' => $destinationHorsPerimetre->id,
        'motif' => 'Tentative hors périmètre',
    ])->assertNotFound();
});
