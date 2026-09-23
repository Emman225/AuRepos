<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Models\ChargeProprietaire;
use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Domain\Partenaires\Services\RelevesProprietaires;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\RelevePropretaireMail;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

function laisserUnBonConsomme(Proprietaire $proprietaire): void
{
    $logement = Logement::factory()->create(['residence_id' => Residence::factory()->create(['proprietaire_id' => $proprietaire->id])]);
    $sejour = Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Parti, 'arrivee' => '2026-11-01', 'depart' => '2026-11-04',
        'client_id' => User::factory()->create()->id,
    ]);
    BonDeMiseADisposition::create([
        'sejour_id' => $sejour->id, 'proprietaire_id' => $proprietaire->id, 'nuitees' => 3,
        'prix_proprietaire_par_nuit' => 15000, 'etat' => 'valide', 'valide_automatiquement' => true, 'valide_le' => now(),
    ]);
}

it('génère le relevé du mois : PDF conservé, attestation de retenue conservée, envoi une seule fois', function (): void {
    $proprietaire = Proprietaire::factory()->create(['regime_fiscal' => RegimeFiscal::Aucun]);
    laisserUnBonConsomme($proprietaire);

    $releves = app(RelevesProprietaires::class);
    $releve = $releves->genererPourLeMois($proprietaire, Carbon::parse('2026-11-15'));

    expect($releve->periode->toDateString())->toBe('2026-11-01')
        ->and($releve->nuitees_consommees)->toBe(3)
        ->and($releve->montant_brut)->toBe(45000);

    $pdf = $releves->pdf($releve);
    expect($pdf)->toStartWith('%PDF');
    expect(Storage::disk('local')->exists($releve->refresh()->chemin_pdf))->toBeTrue();

    $attestation = $releves->attestationPdf($releve);
    expect($attestation)->toStartWith('%PDF');

    expect($releves->envoyerUneFois($releve))->toBeTrue();
    Mail::assertQueued(RelevePropretaireMail::class, fn ($m) => $m->releve->is($releve));
    // Un second appel n'envoie plus : l'envoi est unique (CdC § 8.4).
    expect($releves->envoyerUneFois($releve->refresh()))->toBeFalse();
});

it('est idempotent : un relevé déjà généré pour ce mois n’est jamais régénéré ni ses charges déduites deux fois', function (): void {
    $proprietaire = Proprietaire::factory()->create(['regime_fiscal' => RegimeFiscal::Aucun]);
    laisserUnBonConsomme($proprietaire);
    $administrateur = User::factory()->profil(Profil::Administrateur)->create();
    ChargeProprietaire::create([
        'proprietaire_id' => $proprietaire->id, 'periode' => '2026-11-01', 'nature' => 'menage',
        'montant' => 5000, 'motif' => 'Ménage', 'cree_par' => $administrateur->id,
    ]);

    $releves = app(RelevesProprietaires::class);
    $premier = $releves->genererPourLeMois($proprietaire, Carbon::parse('2026-11-01'));
    $second = $releves->genererPourLeMois($proprietaire, Carbon::parse('2026-11-20')); // même mois, jour différent

    expect($second->is($premier))->toBeTrue()
        ->and(RelevePropretaire::count())->toBe(1)
        ->and(ChargeProprietaire::sole()->releve_id)->toBe($premier->id);
});

it('bloque la génération pour un propriétaire tiers sans régime fiscal renseigné', function (): void {
    $proprietaire = Proprietaire::factory()->create(); // regime_fiscal non renseigné
    laisserUnBonConsomme($proprietaire);

    expect(fn () => app(RelevesProprietaires::class)->genererPourLeMois($proprietaire, Carbon::parse('2026-11-01')))
        ->toThrow(ErreurMetier::class);
});

it('génère quand même les relevés des AUTRES propriétaires quand l’un d’eux a un dossier incomplet', function (): void {
    $incomplet = Proprietaire::factory()->create();
    laisserUnBonConsomme($incomplet);
    $complet = Proprietaire::factory()->create(['regime_fiscal' => RegimeFiscal::Aucun]);
    laisserUnBonConsomme($complet);

    $releves = app(RelevesProprietaires::class)->genererPourLeMoisTousProprietaires(Carbon::parse('2026-11-01'));

    expect(collect($releves)->pluck('proprietaire_id')->all())->toBe([$complet->id]);
});

it('génère aussi le relevé du compte propriétaire interne, sans aucune retenue', function (): void {
    $interne = Proprietaire::factory()->interne()->create();
    laisserUnBonConsomme($interne);

    $releve = app(RelevesProprietaires::class)->genererPourLeMois($interne, Carbon::parse('2026-11-01'));

    expect($releve->retenue_taux)->toBe(0.0)->and($releve->retenue_montant)->toBe(0)->and($releve->montant_net)->toBe($releve->montant_brut);
});
