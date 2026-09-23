<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Services\Missions;
use App\Domain\Maintenance\Enums\EtatDuTicketMaintenance;
use App\Domain\Maintenance\Enums\UrgenceTicket;
use App\Domain\Maintenance\Services\TicketsMaintenance;
use App\Domain\Sejours\Services\Calendrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Avant toute connexion : reculer l'horloge APRÈS l'émission du jeton rendrait sa
    // date d'émission (« iat ») future, et le jeton serait refusé (cf. ProlongationTest).
    Carbon::setTestNow('2026-09-10 09:00:00');

    $this->residence = Residence::factory()->create();
    $this->logement = Logement::factory()->create(['residence_id' => $this->residence->id]);
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
});

it('surface les missions de ménage de la période dans « missions », sans casser la grille existante', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $mission = app(Missions::class)->demander($this->logement, 'Ménage demandé par le back office', $auteur, Carbon::parse('2026-09-15'));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $missions = $reponse->json('data.missions');
    expect($missions)->toHaveCount(1);
    expect($missions[0])
        ->id->toBe($mission->id)
        ->logement_id->toBe($this->logement->id)
        ->type->toBe('menage')
        ->statut->toBe('a_faire')
        ->echeance->toBe('2026-09-15');
});

it('n’affiche pas une mission de ménage hors de la période demandée', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    app(Missions::class)->demander($this->logement, 'Ménage', $auteur, Carbon::parse('2026-01-05'));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.missions'))->toBe([]);
});

it('surface un blocage « maintenance » et un blocage « usage du propriétaire » qui chevauchent la période dans « blocages »', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $calendrier = app(Calendrier::class);
    $maintenance = $calendrier->bloquer($this->logement, Carbon::parse('2026-09-10'), Carbon::parse('2026-09-12'), 'maintenance', null, $auteur);
    $proprietaire = $calendrier->bloquer($this->logement, Carbon::parse('2026-09-20'), Carbon::parse('2026-09-22'), 'usage_proprietaire', null, $auteur);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $blocages = collect($reponse->json('data.blocages'));
    expect($blocages)->toHaveCount(2);
    expect($blocages->firstWhere('id', $maintenance->id))
        ->motif->toBe('maintenance')
        ->debut->toBe('2026-09-10')
        ->fin->toBe('2026-09-12');
    expect($blocages->firstWhere('id', $proprietaire->id))->motif->toBe('usage_proprietaire');
});

it('n’affiche pas un blocage hors de la période demandée', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    app(Calendrier::class)->bloquer($this->logement, Carbon::parse('2026-01-05'), Carbon::parse('2026-01-08'), 'maintenance', null, $auteur);

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.blocages'))->toBe([]);
});

it('marque un logement « ferme » quand sa résidence est en état « Occupée » (bouton propriétaire)', function (): void {
    $this->residence->forceFill(['disponibilite' => Disponibilite::Occupee])->save();

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $logement = collect($reponse->json('data.logements'))->firstWhere('id', $this->logement->id);
    expect($logement['ferme'])->toBeTrue();
});

it('ne marque pas un logement « ferme » quand sa résidence est disponible (par défaut)', function (): void {
    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $logement = collect($reponse->json('data.logements'))->firstWhere('id', $this->logement->id);
    expect($logement['ferme'])->toBeFalse();
});

it('reste défensif quand aucune mission ni aucun blocage n’existe encore : listes vides, aucune erreur', function (): void {
    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.missions'))->toBe([]);
    expect($reponse->json('data.blocages'))->toBe([]);
    expect($reponse->json('data.tickets_maintenance'))->toBe([]);
});

it('surface un ticket de maintenance ouvert NON bloquant, que rien d’autre ne rendrait visible', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $ticket = app(TicketsMaintenance::class)->signaler(
        $this->logement, UrgenceTicket::Haute, 'Chauffe-eau capricieux', null, null, $auteur,
    );

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $tickets = $reponse->json('data.tickets_maintenance');
    expect($tickets)->toHaveCount(1);
    expect($tickets[0])
        ->id->toBe($ticket->id)
        ->logement_id->toBe($this->logement->id)
        ->urgence->toBe('haute')
        ->statut->toBe('ouvert')
        ->bloquant->toBeFalse()
        ->blocage_id->toBeNull();

    // Un ticket non bloquant ne retire aucune date de la vente : rien côté blocages.
    expect($reponse->json('data.blocages'))->toBe([]);
});

it('relie un ticket BLOQUANT à son blocage calendrier, pour que l’écran ne le peigne pas deux fois', function (): void {
    // Le blocage d'un ticket bloquant court d'AUJOURD'HUI (10/09, cf. beforeEach) au 14/09.
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $ticket = app(TicketsMaintenance::class)->signaler(
        $this->logement, UrgenceTicket::Bloquante, 'Fuite majeure', null, null, $auteur, Carbon::parse('2026-09-14'),
    );

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    $tickets = $reponse->json('data.tickets_maintenance');
    expect($tickets)->toHaveCount(1);
    expect($tickets[0])
        ->bloquant->toBeTrue()
        ->blocage_id->toBe($ticket->blocage_calendrier_id)
        ->indisponible_jusquau->toBe('2026-09-14');

    // Le MÊME blocage est bien celui que la grille reçoit par ailleurs (motif « maintenance »).
    $blocages = $reponse->json('data.blocages');
    expect($blocages)->toHaveCount(1);
    expect($blocages[0]['id'])->toBe($ticket->blocage_calendrier_id);
    expect($blocages[0]['motif'])->toBe('maintenance');
});

it('n’affiche pas un ticket de maintenance déjà résolu', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    $ticket = app(TicketsMaintenance::class)->signaler(
        $this->logement, UrgenceTicket::Normale, 'Ampoule grillée', null, null, $auteur,
    );
    $ticket->forceFill(['statut' => EtatDuTicketMaintenance::Resolu])->save();

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.tickets_maintenance'))->toBe([]);
});

it('reste défensif quand il n’y a aucun logement dans le périmètre : listes vides, aucune erreur', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));

    $reponse = test()->getJson('/api/v1/backoffice/planning?du=2026-09-01&au=2026-09-30')->assertOk();

    expect($reponse->json('data.logements'))->toBe([]);
    expect($reponse->json('data.missions'))->toBe([]);
    expect($reponse->json('data.blocages'))->toBe([]);
    expect($reponse->json('data.indicateurs.nuits_disponibles'))->toBe(0);
});
