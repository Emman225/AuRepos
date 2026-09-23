<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->administrateur = User::factory()->profil(Profil::Administrateur)->create();
    $this->gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
});

function connecteEnTantQue(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

// ---------------------------------------------------------------- liste et fiche

it('liste les clients avec leur fiche', function (): void {
    connecteEnTantQue($this->administrateur);
    $client = User::factory()->profil(Profil::Client)->create(['nom' => 'Koné', 'prenoms' => 'Awa']);
    Client::de($client)->update(['nature' => 'b2b', 'raison_sociale' => 'ACME SARL']);

    $reponse = test()->getJson('/api/v1/backoffice/clients')->assertOk();

    $ligne = collect($reponse->json('data.elements'))->firstWhere('id', $client->id);
    expect($ligne['nom_complet'])->toBe('Awa Koné')
        ->and($ligne['nature'])->toBe('b2b')
        ->and($ligne['raison_sociale'])->toBe('ACME SARL')
        ->and($ligne['liste_noire'])->toBeFalse();
});

it('filtre les clients par recherche et par statut', function (): void {
    connecteEnTantQue($this->administrateur);
    $trouve = User::factory()->profil(Profil::Client)->create(['email' => 'cible.unique@exemple.ci']);
    User::factory()->profil(Profil::Client)->create(['email' => 'autre@exemple.ci']);

    $reponse = test()->getJson('/api/v1/backoffice/clients?recherche=cible.unique')->assertOk();

    expect($reponse->json('data.elements'))->toHaveCount(1)
        ->and($reponse->json('data.elements.0.id'))->toBe($trouve->id);
});

it('un gestionnaire peut consulter la liste des clients', function (): void {
    connecteEnTantQue($this->gestionnaire);

    test()->getJson('/api/v1/backoffice/clients')->assertOk();
});

it('un profil hors exploitation n’accède pas aux clients', function (): void {
    connecteEnTantQue(User::factory()->profil(Profil::Proprietaire)->create());

    test()->getJson('/api/v1/backoffice/clients')->assertForbidden();
});

// ---------------------------------------------------------------- bascule de TVA

it('bascule le régime de TVA d’un client, motif obligatoire', function (): void {
    connecteEnTantQue($this->gestionnaire);
    $client = User::factory()->profil(Profil::Client)->create();

    test()->putJson("/api/v1/backoffice/clients/{$client->id}/tva", ['tva_hebergement' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('motif');

    $reponse = test()->putJson("/api/v1/backoffice/clients/{$client->id}/tva", ['tva_hebergement' => false, 'motif' => 'Demande écrite du client.'])
        ->assertOk();

    expect($reponse->json('data.tva_hebergement'))->toBeFalse()
        ->and($reponse->json('data.tva_transfert'))->toBeTrue() // inchangé
        ->and($reponse->json('data.tva_motif'))->toBe('Demande écrite du client.');
    $fiche = Client::de($client->refresh());
    expect($fiche->tva_hebergement)->toBeFalse()->and($fiche->tva_motif_par)->toBe($this->gestionnaire->id);
});

// ---------------------------------------------------------------- identité fiscale (NCC, RCCM)

it('renseigne la raison sociale, le NCC et le RCCM d’un client professionnel', function (): void {
    connecteEnTantQue($this->gestionnaire);
    $client = User::factory()->profil(Profil::Client)->create();
    Client::de($client)->update(['nature' => 'b2b']);

    $reponse = test()->putJson("/api/v1/backoffice/clients/{$client->id}/fiche", [
        'raison_sociale' => 'ACME SARL', 'ncc' => '1234567A', 'rccm' => 'CI-ABJ-2024-B-00001',
    ])->assertOk();

    expect($reponse->json('data.raison_sociale'))->toBe('ACME SARL')
        ->and($reponse->json('data.ncc'))->toBe('1234567A')
        ->and($reponse->json('data.rccm'))->toBe('CI-ABJ-2024-B-00001');
    expect(Client::de($client->refresh())->ncc)->toBe('1234567A');
});

it('refuse la bascule de TVA sans aucun champ', function (): void {
    connecteEnTantQue($this->administrateur);
    $client = User::factory()->profil(Profil::Client)->create();

    test()->putJson("/api/v1/backoffice/clients/{$client->id}/tva", [])->assertUnprocessable();
});

// ---------------------------------------------------------------- liste noire

it('place un client en liste noire avec un motif, réservé à un administrateur', function (): void {
    connecteEnTantQue($this->administrateur);
    $client = User::factory()->profil(Profil::Client)->create();

    $reponse = test()->putJson("/api/v1/backoffice/clients/{$client->id}/liste-noire", [
        'en_liste_noire' => true, 'motif' => 'Impayés répétés et comportement abusif en agence.',
    ])->assertOk();

    expect($reponse->json('data.liste_noire'))->toBeTrue()
        ->and($reponse->json('data.liste_noire_motif'))->toBe('Impayés répétés et comportement abusif en agence.');
    $fiche = Client::de($client->refresh());
    expect($fiche->liste_noire)->toBeTrue()
        ->and($fiche->liste_noire_par)->toBe($this->administrateur->id)
        ->and($fiche->liste_noire_le)->not->toBeNull();
});

it('exige un motif pour mettre en liste noire', function (): void {
    connecteEnTantQue($this->administrateur);
    $client = User::factory()->profil(Profil::Client)->create();

    test()->putJson("/api/v1/backoffice/clients/{$client->id}/liste-noire", ['en_liste_noire' => true])
        ->assertUnprocessable();
});

it('retire un client de la liste noire', function (): void {
    connecteEnTantQue($this->administrateur);
    $client = User::factory()->profil(Profil::Client)->create();
    Client::de($client)->update(['liste_noire' => true, 'liste_noire_motif' => 'Test', 'liste_noire_par' => $this->administrateur->id, 'liste_noire_le' => now()]);

    $reponse = test()->putJson("/api/v1/backoffice/clients/{$client->id}/liste-noire", ['en_liste_noire' => false])
        ->assertOk();

    expect($reponse->json('data.liste_noire'))->toBeFalse();
    $fiche = Client::de($client->refresh());
    expect($fiche->liste_noire)->toBeFalse()->and($fiche->liste_noire_motif)->toBeNull();
});

it('un gestionnaire ne peut pas mettre un client en liste noire', function (): void {
    connecteEnTantQue($this->gestionnaire);
    $client = User::factory()->profil(Profil::Client)->create();

    test()->putJson("/api/v1/backoffice/clients/{$client->id}/liste-noire", [
        'en_liste_noire' => true, 'motif' => 'Impayés répétés.',
    ])->assertForbidden();
});

// ---------------------------------------------------------------- effet sur la réservation

it('bloque la réservation en agence d’un client en liste noire', function (): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $client = User::factory()->create();
    Client::de($client)->update(['liste_noire' => true, 'liste_noire_motif' => 'Test', 'liste_noire_par' => $this->administrateur->id, 'liste_noire_le' => now()]);

    connecteEnTantQue($this->administrateur);
    test()->postJson('/api/v1/backoffice/sejours', [
        'canal' => 'telephone', 'reference_logement' => $logement->reference, 'client_id' => $client->id,
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'client_liste_noire');
});

it('bloque la réservation en libre-service d’un client en liste noire', function (): void {
    $logement = Logement::factory()->create();
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $client = User::factory()->create();
    Client::de($client)->update(['liste_noire' => true, 'liste_noire_motif' => 'Test', 'liste_noire_par' => $this->administrateur->id, 'liste_noire_le' => now()]);

    connecteEnTantQue($client);
    test()->postJson('/api/v1/client/sejours', [
        'reference_logement' => $logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'agence',
    ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'client_liste_noire');
});

// ---------------------------------------------------------------- export (CdC § 6.8)

it('exporte la liste des clients en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecteEnTantQue($this->administrateur);
    User::factory()->profil(Profil::Client)->create(['nom' => 'Kouassi', 'prenoms' => 'Aya']);

    $reponse = test()->getJson('/api/v1/backoffice/clients/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
    expect($reponse->getContent())->not->toBeEmpty();
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /clients/export avant /clients/{client} : "export" n’est jamais pris pour un identifiant', function (): void {
    connecteEnTantQue($this->administrateur);

    test()->getJson('/api/v1/backoffice/clients/export?format=xlsx')->assertOk();
});
