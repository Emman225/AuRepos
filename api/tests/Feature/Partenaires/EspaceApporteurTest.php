<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $this->apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $this->autreApporteur = Apporteur::factory()->create(['pourcentage' => 10]);
});

/** Connecte L'apporteur du jeu de données de ce test — jamais la valeur par défaut d'un beforeEach. */
function seConnecterCommeLApporteur(): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(test()->apporteur->utilisateur));
}

/** Crée un filleul de l'apporteur donné, avec un séjour encaissé jusqu'au bout du circuit. */
function creerUnFilleulEncaisse(Apporteur $apporteur, int $montant = 50000): Sejour
{
    $agence = Agence::factory()->create();
    $caissier = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => $agence->id]);
    [$a1, $a2] = [User::factory()->profil(Profil::Administrateur)->create(), User::factory()->profil(Profil::Administrateur)->create()];
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => $montant]);

    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($caissier, $client, [$sejour->id], $montant, ModeDeReglement::Especes, 'Solde');
    $caisse->valider($r, $a1);
    $caisse->joindreLaPreuve($r->refresh(), $a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $a2);

    return $sejour;
}

it('refuse l’accès sans authentification', function (): void {
    test()->getJson('/api/v1/apporteur/tableau-de-bord')->assertUnauthorized();
});

it('renvoie une 404 propre quand le compte connecté n’a pas de fiche apporteur', function (): void {
    $sansFiche = User::factory()->profil(Profil::Apporteur)->create();
    test()->withToken(auth('api')->login($sansFiche));

    test()->getJson('/api/v1/apporteur/tableau-de-bord')->assertNotFound();
});

it('un autre profil (client) ne peut pas accéder à l’espace apporteur', function (): void {
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Client)->create()));

    test()->getJson('/api/v1/apporteur/tableau-de-bord')->assertForbidden();
    test()->getJson('/api/v1/apporteur/filleuls')->assertForbidden();
    test()->getJson('/api/v1/apporteur/commissions')->assertForbidden();
});

it('affiche le tableau de bord : filleuls, commissions et solde dû', function (): void {
    creerUnFilleulEncaisse(test()->apporteur, 50000); // commission 5 000
    creerUnFilleulEncaisse(test()->apporteur, 20000); // commission 2 000
    creerUnFilleulEncaisse(test()->autreApporteur, 100000); // jamais compté ici

    seConnecterCommeLApporteur();
    test()->getJson('/api/v1/apporteur/tableau-de-bord')->assertOk()
        ->assertJsonPath('data.nombre_filleuls', 2)
        ->assertJsonPath('data.nombre_commissions', 2)
        ->assertJsonPath('data.solde_du', 7000);
});

it('liste SES filleuls, jamais ceux d’un autre apporteur, sans donnée sensible', function (): void {
    creerUnFilleulEncaisse(test()->apporteur);
    creerUnFilleulEncaisse(test()->autreApporteur);

    seConnecterCommeLApporteur();
    $reponse = test()->getJson('/api/v1/apporteur/filleuls')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1);
    expect($reponse->json('data.0'))->not->toHaveKeys(['email', 'telephone']);
});

it('liste SES commissions, jamais celles d’un autre apporteur', function (): void {
    $sejour = creerUnFilleulEncaisse(test()->apporteur, 40000);
    creerUnFilleulEncaisse(test()->autreApporteur, 40000);

    seConnecterCommeLApporteur();
    $reponse = test()->getJson('/api/v1/apporteur/commissions')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1)
        ->and($reponse->json('data.0.montant'))->toBe(4000)
        ->and($reponse->json('data.0.sejour_reference'))->toBe($sejour->reference);
});
