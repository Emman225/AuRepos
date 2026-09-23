<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\BaremeTransfert;
use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Vehicule;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Mail\TransfertAffecteMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();

    $region = Region::create(['nom' => 'District autonome d’Abidjan']);
    $ville = Ville::create(['region_id' => $region->id, 'nom' => 'Abidjan']);
    $this->commune = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $this->type = TypeVehicule::create(['nom' => 'Berline', 'capacite' => 4]);
    BaremeTransfert::factory()->create(['commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 15000]);

    $this->chauffeur = Chauffeur::factory()->create();
    $this->vehicule = Vehicule::factory()->create(['chauffeur_id' => $this->chauffeur->id]);
    $this->autreChauffeur = Chauffeur::factory()->create();
    $this->autreVehicule = Vehicule::factory()->create(['chauffeur_id' => $this->autreChauffeur->id]);
});

function connecterCommeChauffeur(Chauffeur $chauffeur): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($chauffeur->utilisateur));
}

/** Demande, affecte à CE chauffeur et clôture un transfert avec le code réel — de bout en bout. */
function creerUnTransfertTermine(Chauffeur $chauffeur, Vehicule $vehicule, int $montantVerseAuChauffeur, array $donnees = []): void
{
    $t = test();
    $gestion = app(GestionDesTransferts::class);

    $client = User::factory()->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id]);
    $transfert = $gestion->demander($sejour, array_merge([
        'lieu_de_prise_en_charge' => 'Aéroport', 'commune_id' => $t->commune->id, 'type_vehicule_souhaite_id' => $t->type->id,
        'date_heure_prevue' => '2026-11-10 14:00:00', 'nombre_passagers' => 2,
    ], $donnees));
    $transfert = $gestion->affecter($transfert, $chauffeur, $vehicule, $montantVerseAuChauffeur);

    $code = null;
    Mail::assertQueued(TransfertAffecteMail::class, function ($m) use (&$code) {
        $code = $m->code;

        return true;
    });
    Mail::fake(); // on relance le compteur pour ne pas cumuler les assertions entre appels

    $gestion->cloturerParCode($transfert, $code, $chauffeur->utilisateur);
}

// ---------------------------------------------------------------- accès et isolation

it('refuse l’accès sans authentification', function (): void {
    test()->getJson('/api/v1/chauffeur/tableau-de-bord')->assertUnauthorized();
});

it('renvoie une 404 propre quand le compte connecté n’a pas de fiche chauffeur', function (): void {
    $sansFiche = User::factory()->profil(Profil::Chauffeur)->create();
    test()->withToken(auth('api')->login($sansFiche));

    test()->getJson('/api/v1/chauffeur/tableau-de-bord')->assertNotFound();
});

it('un autre profil ne peut pas accéder à l’espace chauffeur', function (): void {
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Client)->create()));

    test()->getJson('/api/v1/chauffeur/transferts')->assertForbidden();
});

it('liste SES transferts, jamais ceux d’un autre chauffeur', function (): void {
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 10000);
    creerUnTransfertTermine($this->autreChauffeur, $this->autreVehicule, 10000);

    connecterCommeChauffeur($this->chauffeur);
    $reponse = test()->getJson('/api/v1/chauffeur/transferts')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1);
    // Le code de prise en charge ne se lit jamais ici.
    expect($reponse->getContent())->not->toContain('code_prise_en_charge');
});

// ---------------------------------------------------------------- véhicules (self-service)

it('gère SES propres véhicules', function (): void {
    connecterCommeChauffeur($this->chauffeur);

    test()->postJson('/api/v1/chauffeur/vehicules', ['type_vehicule_id' => $this->type->id, 'immatriculation' => 'CI-1234-AB'])
        ->assertCreated();

    $reponse = test()->getJson('/api/v1/chauffeur/vehicules')->assertOk();
    expect($reponse->json('data'))->toHaveCount(2); // celui du beforeEach + celui créé ici
});

// ---------------------------------------------------------------- gains

it('calcule le total gagné et le solde dû après plusieurs transferts terminés', function (): void {
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 10000);
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 7000);
    // Sur un AUTRE chauffeur : jamais compté ici.
    creerUnTransfertTermine($this->autreChauffeur, $this->autreVehicule, 50000);

    connecterCommeChauffeur($this->chauffeur);
    $reponse = test()->getJson('/api/v1/chauffeur/gains')->assertOk();

    expect($reponse->json('data.total_gagne'))->toBe(17000)
        ->and($reponse->json('data.solde_du'))->toBe(17000);
});

it('déduit du solde dû ce qui a déjà été décaissé au chauffeur', function (): void {
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 10000);
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 7000);

    $agence = Agence::factory()->create();
    $administrateur = User::factory()->profil(Profil::Administrateur)->create(['agence_id' => $agence->id]);
    $a1 = User::factory()->profil(Profil::Administrateur)->create();
    $a2 = User::factory()->profil(Profil::Administrateur)->create();
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnDecaissement($administrateur, $this->chauffeur->utilisateur, 10000, ModeDeReglement::Especes, 'Versement chauffeur');
    $caisse->valider($r, $a1);
    // Le même administrateur joint la preuve ET finalise (Caisse::finaliser l'exige).
    $caisse->joindreLaPreuve($r->refresh(), $a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $a2);

    connecterCommeChauffeur($this->chauffeur);
    $reponse = test()->getJson('/api/v1/chauffeur/gains')->assertOk();

    expect($reponse->json('data.total_gagne'))->toBe(17000)
        ->and($reponse->json('data.solde_du'))->toBe(7000);
});

it('affiche le tableau de bord : transferts affectés en attente et gains', function (): void {
    creerUnTransfertTermine($this->chauffeur, $this->vehicule, 10000);

    $client = User::factory()->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id]);
    $enAttente = app(GestionDesTransferts::class)->demander($sejour, [
        'lieu_de_prise_en_charge' => 'Gare', 'commune_id' => $this->commune->id, 'type_vehicule_souhaite_id' => $this->type->id,
        'date_heure_prevue' => '2026-12-01 09:00:00', 'nombre_passagers' => 1,
    ]);
    app(GestionDesTransferts::class)->affecter($enAttente, $this->chauffeur, $this->vehicule, 5000);

    connecterCommeChauffeur($this->chauffeur);
    test()->getJson('/api/v1/chauffeur/tableau-de-bord')->assertOk()
        ->assertJsonPath('data.nombre_transferts_affectes', 1)
        ->assertJsonPath('data.total_gagne', 10000);
});
