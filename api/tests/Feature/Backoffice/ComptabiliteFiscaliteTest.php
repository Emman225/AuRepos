<?php

use App\Domain\Caisse\Models\Imputation;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

function connecteAdminFiscalite(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

/** Séjour dont 50 % du net à payer est déjà encaissé (règlement effectué, imputé). */
function sejourFiscalDemiRegle(): Sejour
{
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $sejour = Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-05', 'depart' => '2026-11-08',
        'devis' => ['hebergement_net_ht' => 90000, 'total_ht' => 90000, 'total_tva' => 16200, 'tdt' => 900, 'taxe_de_sejour' => 3000, 'autres_taxes' => 3900],
        'net_a_payer' => 110100,
    ]);

    $reglement = reglementEffectue([
        'sens' => 'encaissement', 'guichet' => 'sejours', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => User::factory()->create()->id, 'montant' => 55050, 'mode' => 'especes', 'notes' => 'Acompte',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => now(),
    ]);
    Imputation::create(['reglement_id' => $reglement->id, 'affaire_type' => 'sejour', 'affaire_id' => $sejour->id, 'montant' => 55050]);

    return $sejour;
}

it('calcule la TVA facturée et encaissée au prorata du règlement', function (): void {
    sejourFiscalDemiRegle();
    connecteAdminFiscalite();

    test()->getJson('/api/v1/backoffice/comptabilite/tva?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.facture', 16200)
        ->assertJsonPath('data.totaux.encaisse_au_prorata', 8100)
        ->assertJsonPath('data.totaux.reste_a_encaisser', 8100);
});

it('fait le même calcul, au prorata, pour la taxe de développement touristique', function (): void {
    sejourFiscalDemiRegle();
    connecteAdminFiscalite();

    test()->getJson('/api/v1/backoffice/comptabilite/tdt?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.facture', 900)
        ->assertJsonPath('data.totaux.encaisse_au_prorata', 450);
});

it('additionne la taxe de séjour collectée, par résidence et par mois', function (): void {
    sejourFiscalDemiRegle();
    connecteAdminFiscalite();

    test()->getJson('/api/v1/backoffice/comptabilite/taxe-sejour?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.total', 3000)
        ->assertJsonPath('data.lignes.0.taxe_de_sejour', 3000)
        ->assertJsonPath('data.lignes.0.mois', '2026-11');
});
