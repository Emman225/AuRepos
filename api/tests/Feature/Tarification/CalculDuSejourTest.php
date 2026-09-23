<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Calcul\DemandeDeCalcul;
use App\Domain\Tarification\Models\Supplement;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
| CAS CHIFFRÉS — calculés à la main d'après la formule du cahier des charges (§ 5.4).
| Logement : 30 000 F HT la nuit, caution 50 000 F, capacité de base 2, maximale 4.
| Taux par défaut : TVA 18 %, TDT 3 %, taxe de séjour 0.
| Le lundi 5 octobre 2026 ouvre une semaine sans week-end jusqu'au jeudi.
*/

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');
    $this->logement = Logement::factory()->create(['caution' => 50000, 'capacite_de_base' => 2, 'capacite_maximale' => 4]);
    $this->logement->forceFill(['prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
});

function regler(string $onglet, array $valeurs): void
{
    app(Parametres::class)->enregistrer($onglet, $valeurs, User::factory()->profil(Profil::SuperAdministrateur)->create());
}

function devis(array $options = []): array
{
    return app(CalculDuSejour::class)->calculer(new DemandeDeCalcul(...[
        'logement' => test()->logement->refresh(),
        'arrivee' => Carbon::parse('2026-10-05'), 'depart' => Carbon::parse('2026-10-08'), // 3 nuits, lundi → jeudi
        'adultes' => 2, ...$options,
    ]))->toArray();
}

it('cas A — séjour simple : 3 nuits à 30 000 F', function (): void {
    $d = devis();

    expect($d['hebergement_net_ht'])->toBe(90000)     // 3 × 30 000
        ->and($d['total_tva'])->toBe(16200)           // 90 000 × 18 %
        ->and($d['total_ttc'])->toBe(106200)
        ->and($d['tdt'])->toBe(3186)                  // 106 200 × 3 %
        ->and($d['taxe_de_sejour'])->toBe(0)
        ->and($d['net_a_payer'])->toBe(109386)
        ->and($d['caution'])->toBe(50000)             // à part : jamais dans la base fiscale
        ->and($d['total_avec_caution'])->toBe(159386)
        ->and($d['taux'])->toMatchArray(['tva' => 18.0, 'tdt' => 3.0]);
});

it('cas B — remise de 10 % : elle se calcule sur le hors taxes', function (): void {
    $d = devis(['remisePourcentage' => 10.0]);

    expect($d['remise_ht'])->toBe(9000)
        ->and($d['hebergement_net_ht'])->toBe(81000)
        ->and($d['total_tva'])->toBe(14580)
        ->and($d['total_ttc'])->toBe(95580)
        ->and($d['tdt'])->toBe(2867)                  // 2 867,4 → 2 867
        ->and($d['net_a_payer'])->toBe(98447);
});

it('cas C — taxe de séjour : par nuitée et par occupant, enfants exonérés, hors base TVA', function (): void {
    regler('taxes', ['sejour_montant' => 500]);

    $d = devis(['adultes' => 2, 'enfants' => 1]);

    expect($d['occupants_taxables'])->toBe(2)
        ->and($d['taxe_de_sejour'])->toBe(3000)       // 500 × 3 nuits × 2 adultes
        // La taxe de séjour n'entre ni dans la base de TVA ni dans celle de la TDT : mêmes montants qu'au cas A.
        ->and($d['total_tva'])->toBe(16200)
        ->and($d['tdt'])->toBe(3186)
        ->and($d['autres_taxes'])->toBe(6186)         // TDT 3 186 + taxe de séjour 3 000
        ->and($d['net_a_payer'])->toBe(112386);       // 109 386 + 3 000
});

it('cas C bis — taxe de séjour par logement, enfants taxés', function (): void {
    regler('taxes', ['sejour_montant' => 500, 'sejour_base' => 'logement', 'sejour_enfants_exoneres' => false]);

    expect(devis(['adultes' => 2, 'enfants' => 1])['taxe_de_sejour'])->toBe(1500); // 500 × 3 nuits, quel que soit le nombre d'occupants
});

it('cas D — client exonéré de TVA (TVAD / TVAC) : la TDT reste due', function (): void {
    $d = devis(['tvaHebergementApplicable' => false]);

    expect($d['total_tva'])->toBe(0)
        ->and($d['tdt'])->toBe(2700)                  // 90 000 × 3 %
        ->and($d['net_a_payer'])->toBe(92700)
        ->and($d['taux']['tva'])->toBe(0.0);
});

it('cas E — transfert : sa TVA se bascule séparément de celle de l’hébergement', function (): void {
    $avecTva = devis(['transfertHt' => 15000]);
    expect($avecTva['tva_transfert'])->toBe(2700)
        ->and($avecTva['total_ttc'])->toBe(123900)    // 106 200 + 17 700
        ->and($avecTva['tdt'])->toBe(3717)
        ->and($avecTva['net_a_payer'])->toBe(127617);

    $sansTvaTransfert = devis(['transfertHt' => 15000, 'tvaTransfertApplicable' => false]);
    expect($sansTvaTransfert['tva_transfert'])->toBe(0)
        ->and($sansTvaTransfert['tva_hebergement_et_extras'])->toBe(16200);

    regler('taxes', ['tva_sur_transferts' => false]);
    expect(devis(['transfertHt' => 15000])['tva_transfert'])->toBe(0);
});

it('cas F — extras : ils entrent dans la base de TVA et de TDT', function (): void {
    $d = devis(['extras' => [['libelle' => 'Petit-déjeuner × 6', 'montant_ht' => 12000]]]);

    expect($d['extras_ht'])->toBe(12000)
        ->and($d['total_tva'])->toBe(18360)           // (90 000 + 12 000) × 18 %
        ->and($d['total_ttc'])->toBe(120360);
});

it('cas G — occupant supplémentaire au-delà de la capacité de base', function (): void {
    Supplement::create(['code' => 'occupant_supplementaire', 'nom' => 'Occupant supplémentaire', 'mode' => 'par_nuit_et_par_personne', 'montant' => 5000]);

    $d = devis(['adultes' => 3]);                     // capacité de base 2 → 1 occupant en plus

    expect($d['supplements'][0])->toMatchArray(['code' => 'occupant_supplementaire', 'quantite' => 3, 'montant' => 15000])
        ->and($d['hebergement_net_ht'])->toBe(105000)
        ->and(devis(['adultes' => 2])['supplements'])->toBe([]);
});

it('cas H — week-end : seules les nuits du vendredi et du samedi', function (): void {
    Supplement::create(['code' => 'week_end', 'nom' => 'Week-end', 'mode' => 'par_nuit', 'montant' => 4000]);

    // Vendredi 9 → lundi 12 octobre : nuits de vendredi, samedi et dimanche.
    $d = devis(['arrivee' => Carbon::parse('2026-10-09'), 'depart' => Carbon::parse('2026-10-12')]);

    expect($d['supplements'][0])->toMatchArray(['code' => 'week_end', 'quantite' => 2, 'montant' => 8000])
        ->and(devis()['supplements'])->toBe([]);      // lundi → jeudi : aucun
});

it('cas I — arrivée et départ tardifs, et supplément propre au type qui prime sur le général', function (): void {
    Supplement::create(['code' => 'arrivee_tardive', 'nom' => 'Arrivée tardive', 'mode' => 'forfait', 'montant' => 10000]);
    Supplement::create(['code' => 'arrivee_tardive', 'nom' => 'Arrivée tardive (3 pièces)', 'mode' => 'forfait', 'montant' => 7000, 'type_logement_id' => $this->logement->type_logement_id]);
    Supplement::create(['code' => 'depart_tardif', 'nom' => 'Départ tardif', 'mode' => 'forfait', 'montant' => 8000]);

    $d = devis(['arriveeTardive' => true, 'departTardif' => true]);

    expect(array_column($d['supplements'], 'montant', 'code'))->toBe(['arrivee_tardive' => 7000, 'depart_tardif' => 8000])
        ->and(devis(['arriveeTardive' => false])['supplements'])->toBe([]);
});

it('cas J — code promo et points ne font jamais descendre sous le minimum à payer', function (): void {
    $raisonnable = devis(['reductions' => [['libelle' => 'Code BIENVENUE', 'montant' => 10000]]]);
    expect($raisonnable['reductions_ht'])->toBe(10000)->and($raisonnable['reductions_plafonnees'])->toBeFalse()
        ->and($raisonnable['hebergement_net_ht'])->toBe(80000);

    // 500 000 F de points sur un séjour de 90 000 F : plafonné au minimum à payer (1 000 F par défaut).
    $abusif = devis(['reductions' => [['libelle' => 'Points de fidélité', 'montant' => 500000]]]);
    expect($abusif['reductions_plafonnees'])->toBeTrue()
        ->and($abusif['net_a_payer'])->toBeGreaterThanOrEqual(1000)->toBeLessThan(1010)
        ->and($abusif['hebergement_net_ht'])->toBeGreaterThan(0);
});

it('cas K — un prix négocié avec le client prime sur toute la grille', function (): void {
    $d = devis(['tarifNegocieParNuit' => 24000]);

    expect($d['hebergement_brut_ht'])->toBe(72000)
        ->and($d['nuitees'][0]['origine'])->toBe('prix négocié du client');
});

it('arrondit chaque taxe au franc, à l’arrondi commercial, sans erreur de virgule flottante', function (): void {
    expect(CalculDuSejour::pourcentage(50, 1.0))->toBe(1)          // 0,5 monte
        ->and(CalculDuSejour::pourcentage(49, 1.0))->toBe(0)       // 0,49 descend
        ->and(CalculDuSejour::pourcentage(81000, 18.0))->toBe(14580)
        ->and(CalculDuSejour::pourcentage(95580, 3.0))->toBe(2867)
        ->and(CalculDuSejour::pourcentage(100000, 7.5))->toBe(7500)
        // 1,15 × 100 vaut 114,99999… en virgule flottante : un calcul naïf donnerait 114.
        ->and(CalculDuSejour::pourcentage(10000, 1.15))->toBe(115)
        ->and(CalculDuSejour::pourcentage(0, 18.0))->toBe(0);
});

it('fige les taux dans le devis : un changement de paramètre ne touche pas un devis déjà produit', function (): void {
    $avant = devis();
    regler('taxes', ['tva' => 9, 'tdt' => 5]);
    $apres = devis();

    expect($avant['taux']['tva'])->toBe(18.0)->and($avant['net_a_payer'])->toBe(109386)
        ->and($apres['taux']['tva'])->toBe(9.0)->and($apres['total_tva'])->toBe(8100);
});

it('refuse les demandes impossibles', function (array $options, string $code): void {
    $this->logement->forceFill(['duree_minimale' => 2, 'duree_maximale' => 30])->save();

    expect(fn () => devis($options))->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe($code));
})->with([
    'trop d’occupants' => [['adultes' => 5], 'capacite_depassee'],
    'aucun adulte' => [['adultes' => 0, 'enfants' => 2], 'occupants_invalides'],
    'sous la durée minimale' => [['depart' => Carbon::parse('2026-10-06')], 'duree_minimale'],
    'au-delà de la durée maximale' => [['depart' => Carbon::parse('2026-12-01')], 'duree_maximale'],
    'départ avant l’arrivée' => [['depart' => Carbon::parse('2026-10-01')], 'periode_invalide'],
    'remise de 150 %' => [['remisePourcentage' => 150.0], 'remise_invalide'],
]);

// ---------------------------------------------------------------- par l'API

it('donne au public le prix recalculé par le serveur, sans prix propriétaire ni marge', function (): void {
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie])->save();
    $adresse = "/api/v1/catalogue/logements/{$this->logement->refresh()->reference}/estimation";

    $reponse = test()->postJson($adresse, ['arrivee' => '2026-10-05', 'depart' => '2026-10-08', 'adultes' => 2])->assertOk();

    expect($reponse->json('data.net_a_payer'))->toBe(109386)
        ->and($reponse->getContent())->not->toContain('22000')->not->toContain('marge')->not->toContain('proprietaire');

    // Un prix envoyé par le client est ignoré : le serveur recalcule.
    test()->postJson($adresse, ['arrivee' => '2026-10-05', 'depart' => '2026-10-08', 'adultes' => 2, 'net_a_payer' => 1, 'remisePourcentage' => 99, 'remise_pourcentage' => 99])
        ->assertOk()->assertJsonPath('data.net_a_payer', 109386)->assertJsonPath('data.remise_ht', 0);
});

it('refuse au public une date passée et un logement non publié', function (): void {
    $adresse = "/api/v1/catalogue/logements/{$this->logement->refresh()->reference}/estimation";
    test()->postJson($adresse, ['arrivee' => '2026-10-05', 'depart' => '2026-10-08', 'adultes' => 2])->assertNotFound(); // brouillon

    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie])->save();
    test()->postJson($adresse, ['arrivee' => '2026-09-01', 'depart' => '2026-09-05', 'adultes' => 2])->assertStatus(422);
    test()->postJson($adresse, ['arrivee' => '2026-10-05', 'depart' => '2026-10-08', 'adultes' => 9])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'capacite_depassee');
});

it('laisse la réception chiffrer avec remise, extras et transfert, mais pas un client', function (): void {
    $saisie = [
        'logement_id' => $this->logement->id, 'arrivee' => '2026-10-05', 'depart' => '2026-10-08', 'adultes' => 2,
        'remise_pourcentage' => 10, 'transfert_ht' => 15000, 'extras' => [['libelle' => 'Ménage', 'montant_ht' => 5000]],
    ];

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()))
        ->postJson('/api/v1/backoffice/sejours/calcul', $saisie)->assertOk()
        ->assertJsonPath('data.remise_ht', 9000)->assertJsonPath('data.extras_ht', 5000)->assertJsonPath('data.transfert_ht', 15000);

    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Client)->create()))
        ->postJson('/api/v1/backoffice/sejours/calcul', $saisie)->assertForbidden();
});
