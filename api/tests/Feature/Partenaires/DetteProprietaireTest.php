<?php

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Models\ChargeProprietaire;
use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Domain\Partenaires\Services\DetteProprietaire;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

/** Un bon VALIDÉ, dont le séjour est réellement parti — la seule chose que la dette compte (CdC § 7.2). */
function bonConsomme(Proprietaire $proprietaire, array $sejourAttrs = [], array $bonAttrs = []): BonDeMiseADisposition
{
    $logement = Logement::factory()->create(['residence_id' => Residence::factory()->create(['proprietaire_id' => $proprietaire->id])]);
    $sejour = Sejour::factory()->create(array_merge([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Parti,
        'arrivee' => '2026-11-01', 'depart' => '2026-11-04', 'client_id' => User::factory()->create()->id,
    ], $sejourAttrs));

    return BonDeMiseADisposition::create(array_merge([
        'sejour_id' => $sejour->id, 'proprietaire_id' => $proprietaire->id, 'nuitees' => $sejour->nombreDeNuits(),
        'prix_proprietaire_par_nuit' => 15000, 'etat' => 'valide', 'valide_automatiquement' => true, 'valide_le' => now(),
    ], $bonAttrs));
}

// ---------------------------------------------------------------- nuitées consommées, jamais réservées

it('ne compte que les bons VALIDÉS dont le séjour est réellement parti — jamais un bon en attente ni un séjour encore confirmé', function (): void {
    $proprietaire = Proprietaire::factory()->create();
    bonConsomme($proprietaire); // 3 nuits à 15 000 F, comptées

    // Bon en attente : le propriétaire n'a pas encore validé, rien n'est dû.
    bonConsomme($proprietaire, bonAttrs: ['etat' => 'en_attente']);
    // Séjour confirmé mais pas encore parti : nuitées RÉSERVÉES, pas consommées.
    bonConsomme($proprietaire, sejourAttrs: ['etat' => EtatDuSejour::Confirme]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    expect($calcul['nuitees_consommees'])->toBe(3)->and($calcul['brut'])->toBe(3 * 15000);
});

// ---------------------------------------------------------------- deux modes de rémunération

it('calcule le brut en mode « prix négocié » : prix propriétaire par nuit figé sur le bon × nuitées', function (): void {
    $proprietaire = Proprietaire::factory()->create(); // mode_remuneration par défaut : prix_negocie
    bonConsomme($proprietaire, bonAttrs: ['prix_proprietaire_par_nuit' => 18000, 'nuitees' => 3]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    expect($calcul['brut'])->toBe(3 * 18000);
});

it('calcule le brut en mode « commission » : hébergement net HT figé sur le devis × (1 − commission)', function (): void {
    $proprietaire = Proprietaire::factory()->create(['mode_remuneration' => ModeDeRemuneration::Commission, 'taux_commission' => 25]);
    bonConsomme($proprietaire, sejourAttrs: ['devis' => ['hebergement_net_ht' => 90000]]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    // 90 000 × (1 − 25 %) = 67 500, jamais le prix_proprietaire_par_nuit du bon (mode commission : ignoré).
    expect($calcul['brut'])->toBe(67500);
});

// ---------------------------------------------------------------- charges refacturées

it('déduit les charges refacturées de la période, et ne les déduit plus une fois reprises sur un relevé', function (): void {
    $proprietaire = Proprietaire::factory()->create();
    bonConsomme($proprietaire); // brut = 45 000

    $administrateur = User::factory()->profil(Profil::Administrateur)->create();
    ChargeProprietaire::create([
        'proprietaire_id' => $proprietaire->id, 'periode' => '2026-11-01', 'nature' => 'menage',
        'montant' => 8000, 'motif' => 'Ménage renforcé après séjour', 'cree_par' => $administrateur->id,
    ]);
    // Une charge déjà REPRISE sur un relevé antérieur ne doit plus jamais être déduite.
    $relevePrecedent = RelevePropretaire::create([
        'proprietaire_id' => $proprietaire->id, 'periode' => '2026-10-01', 'genere_le' => now(),
    ]);
    ChargeProprietaire::create([
        'proprietaire_id' => $proprietaire->id, 'periode' => '2026-11-01', 'nature' => 'reparation',
        'montant' => 5000, 'motif' => 'Déjà sur le relevé précédent', 'cree_par' => $administrateur->id, 'releve_id' => $relevePrecedent->id,
    ]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    // Net = brut − charges − retenue (personne physique, 7,5 % de 45 000 = 3 375) : la charge REPRISE n'y figure pas.
    expect($calcul['charges_refacturees'])->toBe(8000)
        ->and($calcul['net'])->toBe(45000 - 8000 - (int) round(45000 * 0.075));
});

// ---------------------------------------------------------------- part des cautions retenues

it('reverse la part des cautions retenues, nette de la part de l’entreprise fixée sur le mandat', function (): void {
    $proprietaire = Proprietaire::factory()->create(['part_entreprise_cautions' => 30]);
    bonConsomme($proprietaire, sejourAttrs: ['caution_retenue' => 20000]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    // 20 000 × (1 − 30 %) = 14 000 pour le propriétaire.
    expect($calcul['part_cautions'])->toBe(14000);
});

it('retombe sur le paramètre global quand le mandat ne fixe pas de part entreprise sur les cautions', function (): void {
    $proprietaire = Proprietaire::factory()->create(); // part_entreprise_cautions non renseignée sur SA fiche
    app(Parametres::class)->enregistrer('proprietaires', ['part_entreprise_cautions' => 10], User::factory()->profil(Profil::SuperAdministrateur)->create());
    bonConsomme($proprietaire, sejourAttrs: ['caution_retenue' => 10000]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    expect($calcul['part_cautions'])->toBe(9000); // 10 000 × 90 %
});

// ---------------------------------------------------------------- TVA du propriétaire assujetti

it('ajoute la TVA en plus du brut pour un propriétaire assujetti, jamais pour les autres', function (): void {
    $assujetti = Proprietaire::factory()->create(['assujetti_tva' => true]);
    bonConsomme($assujetti); // brut = 45 000, TVA 18 % par défaut

    $nonAssujetti = Proprietaire::factory()->create(['assujetti_tva' => false]);
    bonConsomme($nonAssujetti);

    $dette = app(DetteProprietaire::class);
    expect($dette->calculerPourLaPeriode($assujetti, Carbon::parse('2026-11-01'))['tva'])->toBe((int) round(45000 * 0.18))
        ->and($dette->calculerPourLaPeriode($nonAssujetti, Carbon::parse('2026-11-01'))['tva'])->toBe(0);
});

// ---------------------------------------------------------------- retenue à la source (P3-PRO-05), appliquée ici

it('retient à la source SEULEMENT sur le brut — jamais sur les charges remboursées, la TVA ou la part des cautions', function (): void {
    $proprietaire = Proprietaire::factory()->create(['assujetti_tva' => true, 'part_entreprise_cautions' => 0]); // personne physique : 7,5 %
    bonConsomme($proprietaire, sejourAttrs: ['caution_retenue' => 10000]);
    $administrateur = User::factory()->profil(Profil::Administrateur)->create();
    ChargeProprietaire::create(['proprietaire_id' => $proprietaire->id, 'periode' => '2026-11-01', 'nature' => 'menage', 'montant' => 5000, 'motif' => 'x', 'cree_par' => $administrateur->id]);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($proprietaire, Carbon::parse('2026-11-01'));

    $brut = 45000;
    $tva = (int) round($brut * 0.18);
    $retenue = (int) round($brut * 0.075);
    expect($calcul['retenue_taux'])->toBe(7.5)
        ->and($calcul['retenue_montant'])->toBe($retenue)
        ->and($calcul['net'])->toBe($brut - 5000 + 10000 + $tva - $retenue);
});

it('applique un compte « propriétaire interne » : aucune retenue', function (): void {
    $interne = Proprietaire::factory()->interne()->create();
    bonConsomme($interne);

    $calcul = app(DetteProprietaire::class)->calculerPourLaPeriode($interne, Carbon::parse('2026-11-01'));

    expect($calcul['retenue_taux'])->toBe(0.0)->and($calcul['retenue_montant'])->toBe(0)->and($calcul['net'])->toBe($calcul['brut']);
});

// ---------------------------------------------------------------- solde dû (plafond des demandes de paiement, P3-PRO-04)

it('déduit déjà versé du solde dû, jamais négatif', function (): void {
    $proprietaire = Proprietaire::factory()->create();
    bonConsomme($proprietaire); // net = 45 000 (personne physique, retenue 7,5 % = 3 375 → net 41 625)

    $agence = Agence::factory()->create();
    [$auteur, $validateur, $troisieme] = User::factory()->profil(Profil::Administrateur)->count(3)->create(['agence_id' => $agence->id]);

    $caisse = app(Caisse::class);
    $reglement = $caisse->saisirUnDecaissement($auteur, $proprietaire->utilisateur, 20000, ModeDeReglement::Especes, 'Versement partiel', Guichet::DettesPartenaires);
    $caisse->valider($reglement, $validateur);
    $caisse->joindreLaPreuve($reglement->refresh(), $troisieme, UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'));
    $caisse->finaliser($reglement->refresh(), $troisieme);

    $solde = app(DetteProprietaire::class)->soldeDu($proprietaire);

    expect($solde['deja_verse'])->toBe(20000)->and($solde['solde_du'])->toBe($solde['net'] - 20000);
});

// ---------------------------------------------------------------- régime fiscal obligatoire avant tout reversement

it('bloque le reversement d’un propriétaire tiers sans régime fiscal renseigné', function (): void {
    $proprietaire = Proprietaire::factory()->create(); // regime_fiscal par défaut : non_renseigne

    expect(fn () => app(DetteProprietaire::class)->exigerLeDossierComplet($proprietaire))
        ->toThrow(ErreurMetier::class);
});

it('ne bloque jamais le compte propriétaire interne, régime fiscal ou pas', function (): void {
    $interne = Proprietaire::factory()->interne()->create();

    app(DetteProprietaire::class)->exigerLeDossierComplet($interne);
})->throwsNoExceptions();

it('laisse passer un propriétaire dont le régime fiscal est renseigné', function (): void {
    $proprietaire = Proprietaire::factory()->create(['regime_fiscal' => RegimeFiscal::Aucun]);

    app(DetteProprietaire::class)->exigerLeDossierComplet($proprietaire);
})->throwsNoExceptions();
