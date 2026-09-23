<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Enums\PolitiqueAnnulation;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Calendrier;
use App\Domain\Sejours\Services\CycleDuSejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->logement = Logement::factory()->create(['caution' => 50000, 'politique_annulation' => PolitiqueAnnulation::Moderee]);
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $this->logement->refresh();
    $this->client = User::factory()->create();
    $this->withToken(auth('api')->login($this->client));
});

function reglage(string $onglet, array $valeurs): void
{
    app(Parametres::class)->enregistrer($onglet, $valeurs, User::factory()->profil(Profil::SuperAdministrateur)->create());
}

function demande(array $surcharge = []): array
{
    return [
        'reference_logement' => test()->logement->reference, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13',
        'adultes' => 2, 'mode_reglement' => 'agence', ...$surcharge,
    ];
}

function reserver(array $surcharge = [])
{
    return test()->postJson('/api/v1/client/sejours', demande($surcharge));
}

// ---------------------------------------------------------------- réservation

it('enregistre une demande : prix recalculé par le serveur, valeurs figées, dates prises', function (): void {
    $reponse = reserver(['heure_arrivee_prevue' => '16:30'])->assertCreated();

    $sejour = Sejour::firstOrFail();
    expect($reponse->json('data.reference'))->toMatch('/^SEJ-\d{6}$/')
        ->and($sejour->etat)->toBe(EtatDuSejour::Demande)
        ->and($sejour->client_id)->toBe($this->client->id)
        ->and($sejour->net_a_payer)->toBe(109386)                 // cas A du moteur : 3 nuits à 30 000 F
        ->and($sejour->caution)->toBe(50000)
        ->and($sejour->acompte_exige)->toBe(32816)                // 30 % de 109 386 = 32 815,8 → 32 816
        ->and($sejour->prix_proprietaire_par_nuit)->toBe(22000)
        ->and($sejour->annulation_pourcentage_retenu)->toBe(50.0)
        ->and($sejour->annulation_delai_jours)->toBe(5)
        ->and($sejour->expire_le->format('Y-m-d H:i'))->toBe('2026-10-02 10:00')   // 24 h
        ->and($sejour->devis['taux']['tva'])->toBe(18)
        ->and(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeFalse();
});

it('génère automatiquement une proforma à la réservation, sans valeur fiscale', function (): void {
    reserver()->assertCreated();

    $sejour = Sejour::firstOrFail();
    $facture = Facture::where('sejour_id', $sejour->id)->sole();
    expect($facture->type->value)->toBe('proforma')
        ->and($facture->numero)->toStartWith('PRO-')
        ->and($facture->montant_ttc)->toBe($sejour->net_a_payer)
        ->and($facture->estTransmise())->toBeFalse();
});

it('ignore tout prix, remise ou état envoyé par le client', function (): void {
    reserver(['net_a_payer' => 1, 'remise_pourcentage' => 99, 'etat' => 'confirme', 'caution' => 0, 'acompte_exige' => 0, 'devis' => ['net_a_payer' => 1]])
        ->assertCreated()->assertJsonPath('data.net_a_payer', 109386)->assertJsonPath('data.etat', 'demande')->assertJsonPath('data.caution', 50000);
});

it('fige les valeurs : un changement de paramètre ne touche pas un séjour déjà enregistré', function (): void {
    reserver()->assertCreated();

    reglage('taxes', ['tva' => 9]);
    reglage('sejours', ['taux_acompte' => 80, 'annulation_moderee' => 100]);
    $this->logement->forceFill(['prix_vente' => 99000, 'prix_proprietaire' => 50000])->save();

    $sejour = Sejour::firstOrFail();
    expect($sejour->net_a_payer)->toBe(109386)->and($sejour->acompte_exige)->toBe(32816)
        ->and($sejour->prix_proprietaire_par_nuit)->toBe(22000)->and($sejour->annulation_pourcentage_retenu)->toBe(50.0);
});

it('refuse la seconde réservation des mêmes nuits', function (): void {
    reserver()->assertCreated();

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->create()));

    reserver(['arrivee' => '2026-11-12', 'depart' => '2026-11-15'])->assertStatus(409)->assertJsonPath('errors.code.0', 'dates_indisponibles');
    expect(Sejour::count())->toBe(1);   // rien n'est resté de la tentative refusée
});

it('enregistre les occupants sans jamais renvoyer un numéro de pièce, chiffré en base', function (): void {
    $reponse = reserver(['adultes' => 1, 'enfants' => 1, 'occupants' => [
        ['nom' => 'Koné', 'prenoms' => 'Awa', 'type_piece' => 'cni', 'numero_piece' => 'C0012345678'],
        ['nom' => 'Koné', 'prenoms' => 'Ismaël', 'enfant' => true],
    ]])->assertCreated();

    expect($reponse->json('data.occupants.0.piece_fournie'))->toBeTrue()
        ->and($reponse->json('data.occupants.1.piece_fournie'))->toBeFalse()
        ->and($reponse->getContent())->not->toContain('C0012345678')
        ->and(DB::table('occupants')->value('numero_piece'))->not->toContain('C0012345678');
});

it('refuse les demandes que le cahier des charges interdit', function (array $surcharge, string $code): void {
    reserver($surcharge)->assertStatus(422)->assertJsonPath('errors.code.0', $code);
    expect(Sejour::count())->toBe(0);
})->with([
    'occupants décrits ≠ occupants annoncés' => [['adultes' => 2, 'occupants' => [['nom' => 'Seul']]], 'occupants_incoherents'],
    'trop d’occupants pour le logement' => [['adultes' => 9], 'capacite_depassee'],
    'règlement à terme sans ligne de crédit' => [['mode_reglement' => 'a_terme'], 'client_non_a_terme'],
    'paiement en ligne désactivé' => [['mode_reglement' => 'en_ligne'], 'paiement_en_ligne_indisponible'],
    'logement inconnu' => [['reference_logement' => 'LOG-99999'], 'logement_non_reservable'],
]);

it('refuse le paiement en ligne au-delà du plafond, mais pas l’agence', function (): void {
    reglage('comptant', ['paiement_en_ligne_actif' => true]);
    reglage('general', ['plafond_paiement_en_ligne' => 100000]);

    reserver(['mode_reglement' => 'en_ligne'])->assertStatus(422)->assertJsonPath('errors.code.0', 'plafond_paiement_en_ligne');
    reserver(['mode_reglement' => 'agence'])->assertCreated();
});

it('refuse un logement non publié, ou dont la résidence est fermée par son propriétaire', function (): void {
    $this->logement->residence->update(['disponibilite' => Disponibilite::Occupee]);
    reserver()->assertStatus(422)->assertJsonPath('errors.code.0', 'logement_non_reservable');

    $this->logement->residence->update(['disponibilite' => Disponibilite::Disponible]);
    $this->logement->forceFill(['etat_publication' => EtatPublication::Suspendu])->save();
    reserver()->assertStatus(422)->assertJsonPath('errors.code.0', 'logement_non_reservable');
});

it('exige le bon de commande d’une organisation, pas d’un particulier', function (): void {
    Client::de($this->client)->update(['nature' => 'b2b', 'raison_sociale' => 'ONG Exemple']);

    reserver()->assertStatus(422)->assertJsonPath('errors.code.0', 'bon_de_commande_obligatoire');
    reserver(['bon_de_commande' => 'BC-2026-0457'])->assertCreated()->assertJsonPath('data.bon_de_commande', 'BC-2026-0457');
});

it('applique l’exonération de TVA du client', function (): void {
    Client::de($this->client)->update(['tva_hebergement' => false, 'code_exoneration' => 'TVAC']);

    reserver()->assertCreated()->assertJsonPath('data.net_a_payer', 92700)->assertJsonPath('data.devis.total_tva', 0);   // cas D du moteur
});

// ---------------------------------------------------------------- espace client

it('ne montre à un client que SES séjours, et l’adresse exacte seulement après confirmation', function (): void {
    $this->logement->residence->update(['adresse' => 'Rue des Jardins, lot 45', 'consignes_acces' => 'Code portail 9911']);
    $reference = reserver()->json('data.reference');
    $autre = Sejour::factory()->create(['client_id' => User::factory()->create()->id, 'arrivee' => '2026-12-01', 'depart' => '2026-12-03']);

    expect(array_column(test()->getJson('/api/v1/client/sejours')->assertOk()->json('data.elements'), 'reference'))->toBe([$reference]);
    test()->getJson("/api/v1/client/sejours/{$autre->refresh()->reference}")->assertNotFound();

    $avant = test()->getJson("/api/v1/client/sejours/{$reference}")->assertOk();
    expect($avant->json('data.acces'))->toBeNull()->and($avant->getContent())->not->toContain('Rue des Jardins')->not->toContain('22000');

    app(CycleDuSejour::class)->passer(Sejour::firstWhere('reference', $reference), EtatDuSejour::Confirme, null, ['confirme_le' => now()]);
    test()->getJson("/api/v1/client/sejours/{$reference}")->assertJsonPath('data.acces.adresse', 'Rue des Jardins, lot 45');
});

it('réserve l’espace client au profil client', function (): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()));

    test()->getJson('/api/v1/client/sejours')->assertForbidden();
    reserver()->assertForbidden();
});

// ---------------------------------------------------------------- machine à états

it('ne laisse passer que les transitions du cahier des charges', function (): void {
    $cycle = app(CycleDuSejour::class);
    $sejour = Sejour::factory()->create(['logement_id' => $this->logement->id]);

    foreach ([EtatDuSejour::Arrive, EtatDuSejour::Parti, EtatDuSejour::Cloture, EtatDuSejour::NoShow] as $interdit) {
        expect(fn () => $cycle->passer($sejour, $interdit))->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('transition_impossible'));
    }

    foreach ([EtatDuSejour::Confirme, EtatDuSejour::Arrive, EtatDuSejour::Parti, EtatDuSejour::Cloture] as $suivant) {
        $cycle->passer($sejour, $suivant);
    }

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Cloture)
        ->and(fn () => $cycle->passer($sejour, EtatDuSejour::Annule))->toThrow(ErreurMetier::class)
        ->and(EntreeAudit::where('sujet_type', 'Sejour')->where('action', 'like', 'sejour_%')->count())->toBe(4);
});

it('libère les dates d’un no-show et les garde pour un séjour parti', function (): void {
    $cycle = app(CycleDuSejour::class);
    $calendrier = app(Calendrier::class);
    $periode = [Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')];

    $noShow = Sejour::factory()->create(['logement_id' => $this->logement->id]);
    $calendrier->occuper($noShow);
    $cycle->passer($noShow, EtatDuSejour::Confirme);
    $cycle->passer($noShow, EtatDuSejour::NoShow);
    expect($calendrier->estLibre($this->logement, ...$periode))->toBeTrue();

    $parti = Sejour::factory()->create(['logement_id' => $this->logement->id]);
    $calendrier->occuper($parti);
    foreach ([EtatDuSejour::Confirme, EtatDuSejour::Arrive, EtatDuSejour::Parti] as $etat) {
        $cycle->passer($parti, $etat);
    }
    expect($calendrier->estLibre($this->logement, ...$periode))->toBeFalse();
});

// ---------------------------------------------------------------- annulation et expiration

it('laisse le client annuler sa demande, mais pas un séjour confirmé', function (): void {
    $reference = reserver()->json('data.reference');

    test()->postJson("/api/v1/client/sejours/{$reference}/annulation")->assertOk()->assertJsonPath('data.etat', 'annule')->assertJsonPath('data.annulation.montant_retenu', 0);
    expect(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeTrue();

    $confirme = reserver()->json('data.reference');
    app(CycleDuSejour::class)->passer(Sejour::firstWhere('reference', $confirme), EtatDuSejour::Confirme);
    test()->postJson("/api/v1/client/sejours/{$confirme}/annulation")->assertStatus(409)->assertJsonPath('errors.code.0', 'annulation_a_instruire');
});

it('retient selon la politique FIGÉE : gratuit avant le délai, le pourcentage après', function (): void {
    $reference = reserver()->json('data.reference');            // arrivée le 10/11, politique modérée : 5 jours, 50 %
    $sejour = Sejour::firstWhere('reference', $reference);
    $cycle = app(CycleDuSejour::class);
    $cycle->passer($sejour, EtatDuSejour::Confirme);

    Carbon::setTestNow('2026-11-05 09:00:00');                  // J-5 : encore gratuit
    expect($cycle->montantRetenuSiAnnulation($sejour))->toBe(0);

    Carbon::setTestNow('2026-11-06 09:00:00');                  // J-4 : 50 % de 109 386
    expect($cycle->montantRetenuSiAnnulation($sejour))->toBe(54693);

    $cycle->annuler($sejour, null, 'Empêchement du client');
    expect($sejour->refresh()->montant_retenu_annulation)->toBe(54693)->and($sejour->motif_annulation)->toBe('Empêchement du client');
});

it('fait expirer les demandes non réglées et libère leurs dates, sans toucher aux autres', function (): void {
    $expiree = reserver()->json('data.reference');
    $confirmee = reserver(['arrivee' => '2026-12-01', 'depart' => '2026-12-03'])->json('data.reference');
    app(CycleDuSejour::class)->passer(Sejour::firstWhere('reference', $confirmee), EtatDuSejour::Confirme);

    Carbon::setTestNow('2026-10-02 09:59:00');
    $this->artisan('sejours:expirer-demandes')->expectsOutputToContain('Aucune demande')->assertSuccessful();

    Carbon::setTestNow('2026-10-02 10:01:00');
    $this->artisan('sejours:expirer-demandes')->expectsOutputToContain('1 demande(s) expirée(s)')->assertSuccessful();

    expect(Sejour::firstWhere('reference', $expiree)->etat)->toBe(EtatDuSejour::Annule)
        ->and(Sejour::firstWhere('reference', $confirmee)->etat)->toBe(EtatDuSejour::Confirme)
        ->and(app(Calendrier::class)->estLibre($this->logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-13')))->toBeTrue();
});
