<?php

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Calendrier;
use App\Support\Api\ErreurMetier;
use Database\Factories\ResidenceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-01 10:00:00'));

function publie(array $attributs = [], int $prix = 30000): Logement
{
    $logement = Logement::factory()->create($attributs);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => $prix])->save();

    return $logement->refresh();
}

function sejourner(Logement $l, string $arrivee, string $depart, EtatDuSejour $etat = EtatDuSejour::Confirme): Sejour
{
    $sejour = Sejour::factory()->create(['logement_id' => $l->id, 'arrivee' => $arrivee, 'depart' => $depart, 'etat' => $etat]);
    app(Calendrier::class)->occuper($sejour);

    return $sejour;
}

function chercher(array $criteres = []): array
{
    return array_column(test()->getJson('/api/v1/catalogue/recherche?'.http_build_query($criteres))->assertOk()->json('data.elements'), 'reference');
}

// ---------------------------------------------------------------- la règle : jamais deux séjours la même nuit

it('refuse un second séjour qui chevauche le premier, d’une seule nuit suffit', function (string $arrivee, string $depart): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13');

    expect(fn () => sejourner($logement, $arrivee, $depart))
        ->toThrow(fn (ErreurMetier $e) => expect($e->codeMetier)->toBe('dates_indisponibles'));
})->with([
    'mêmes dates' => ['2026-11-10', '2026-11-13'],
    'déborde par la fin' => ['2026-11-12', '2026-11-15'],
    'déborde par le début' => ['2026-11-08', '2026-11-11'],
    'englobe' => ['2026-11-01', '2026-11-30'],
    'à l’intérieur' => ['2026-11-11', '2026-11-12'],
]);

it('laisse un client arriver le jour où le précédent part, et un autre logement rester libre', function (): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13');

    sejourner($logement, '2026-11-13', '2026-11-15');   // arrive le jour du départ
    sejourner($logement, '2026-11-07', '2026-11-10');   // part le jour de l'arrivée
    sejourner(publie(), '2026-11-10', '2026-11-13');    // même période, autre logement

    expect(DB::table('occupations')->count())->toBe(4);
});

it('fait tenir la règle par la BASE : même en contournant l’application, deux occupations ne se chevauchent pas', function (): void {
    $logement = publie();
    $a = Sejour::factory()->create(['logement_id' => $logement->id]);
    $b = Sejour::factory()->create(['logement_id' => $logement->id]);
    $inserer = fn (int $id) => DB::transaction(fn () => DB::insert(
        "INSERT INTO occupations (logement_id, sejour_id, periode) VALUES (?, ?, daterange('2026-11-10', '2026-11-13', '[)'))", [$logement->id, $id],
    ));

    $inserer($a->id);

    // C'est exactement ce qui arrive à deux clients qui valident au même instant : la base tranche.
    expect(fn () => $inserer($b->id))->toThrow(QueryException::class, 'occupations_sans_chevauchement');
});

it('n’annule pas la transaction en cours quand les dates sont refusées', function (): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13');

    DB::transaction(function () use ($logement): void {
        $sejour = Sejour::factory()->create(['logement_id' => $logement->id]);
        try {
            app(Calendrier::class)->occuper($sejour);
        } catch (ErreurMetier) {
            // L'appelant peut proposer d'autres dates : sa transaction PostgreSQL reste utilisable.
            $sejour->update(['arrivee' => '2026-11-20', 'depart' => '2026-11-22']);
            app(Calendrier::class)->occuper($sejour);
        }
    });

    expect(DB::table('occupations')->count())->toBe(2);
});

it('libère les dates d’un séjour annulé ou en no-show, et déplace celles d’un séjour modifié', function (): void {
    $logement = publie();
    $calendrier = app(Calendrier::class);
    $sejour = sejourner($logement, '2026-11-10', '2026-11-13');

    // Départ anticipé : la nuit du 12 revient à la vente.
    $sejour->update(['depart' => '2026-11-12']);
    $calendrier->occuper($sejour);
    expect($calendrier->estLibre($logement, Carbon::parse('2026-11-12'), Carbon::parse('2026-11-13')))->toBeTrue();

    $sejour->update(['etat' => EtatDuSejour::Annule]);
    $calendrier->occuper($sejour);
    expect($calendrier->estLibre($logement, Carbon::parse('2026-11-10'), Carbon::parse('2026-11-12')))->toBeTrue();

    $noShow = sejourner($logement, '2026-11-10', '2026-11-12');
    $noShow->update(['etat' => EtatDuSejour::NoShow]);
    $calendrier->occuper($noShow);
    expect(DB::table('occupations')->count())->toBe(0);
});

it('garde les dates d’une simple demande, jusqu’à son expiration', function (): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13', EtatDuSejour::Demande);

    expect(app(Calendrier::class)->estLibre($logement, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-12')))->toBeFalse();
});

// ---------------------------------------------------------------- blocages

it('bloque des dates bornes incluses, et refuse de bloquer sur un séjour existant', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));
    $logement = publie();
    $gestionnaire->residences()->attach($logement->residence_id);
    $base = "/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/blocages";
    sejourner($logement, '2026-11-10', '2026-11-13');

    test()->postJson($base, ['debut' => '2026-11-12', 'fin' => '2026-11-14', 'motif' => 'maintenance'])
        ->assertStatus(409)->assertJsonPath('errors.code.0', 'dates_indisponibles');

    $id = test()->postJson($base, ['debut' => '2026-11-20', 'fin' => '2026-11-22', 'motif' => 'maintenance', 'commentaire' => 'Peinture'])->assertCreated()->json('data.id');
    $calendrier = app(Calendrier::class);
    expect($calendrier->estLibre($logement, Carbon::parse('2026-11-22'), Carbon::parse('2026-11-23')))->toBeFalse()   // le 22 est bloqué
        ->and($calendrier->estLibre($logement, Carbon::parse('2026-11-23'), Carbon::parse('2026-11-24')))->toBeTrue()
        ->and(fn () => sejourner($logement, '2026-11-21', '2026-11-25'))->toThrow(ErreurMetier::class);

    test()->deleteJson("{$base}/{$id}")->assertOk();
    expect($calendrier->estLibre($logement, Carbon::parse('2026-11-20'), Carbon::parse('2026-11-23')))->toBeTrue();
});

it('refuse un blocage dans le passé, à l’envers, ou de motif réservé aux canaux', function (array $saisie): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));
    $logement = publie();
    $gestionnaire->residences()->attach($logement->residence_id);

    test()->postJson("/api/v1/backoffice/residences/{$logement->residence_id}/logements/{$logement->id}/blocages", $saisie)->assertStatus(422);
})->with([
    [['debut' => '2026-09-01', 'fin' => '2026-09-05', 'motif' => 'maintenance']],
    [['debut' => '2026-11-10', 'fin' => '2026-11-05', 'motif' => 'maintenance']],
    [['debut' => '2026-11-10', 'fin' => '2026-11-12', 'motif' => 'canal_externe']],
]);

// ---------------------------------------------------------------- recherche publique

it('ne montre que ce qui est réellement réservable', function (): void {
    $libre = publie();
    $occupe = publie();
    sejourner($occupe, '2026-11-10', '2026-11-13');
    $bloque = publie();
    app(Calendrier::class)->bloquer($bloque, Carbon::parse('2026-11-11'), Carbon::parse('2026-11-11'), 'maintenance', null, null);
    $brouillon = Logement::factory()->create();
    $fermee = publie(['residence_id' => Residence::factory()->create(['disponibilite' => Disponibilite::Occupee])]);
    $desactivee = publie(['residence_id' => Residence::factory()->create(['active' => false])]);

    $trouves = chercher(['arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2]);

    expect($trouves)->toBe([$libre->reference])
        ->and($trouves)->not->toContain($occupe->reference, $bloque->reference, $brouillon->refresh()->reference, $fermee->reference, $desactivee->reference);
});

it('retrouve un logement dès que ses dates se libèrent', function (): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13');

    expect(chercher(['arrivee' => '2026-11-12', 'depart' => '2026-11-14']))->toBe([])
        ->and(chercher(['arrivee' => '2026-11-13', 'depart' => '2026-11-15']))->toBe([$logement->reference]);
});

it('filtre par lieu, type, capacité, budget, durée minimale et équipements', function (): void {
    $piscine = Equipement::create(['nom' => 'Piscine', 'portee' => 'residence']);
    $wifi = Equipement::create(['nom' => 'Wifi', 'portee' => 'logement']);
    $marcory = Residence::factory()->create(['quartier_id' => ResidenceFactory::unQuartier('Marcory', 'Zone 4')->id]);
    $marcory->equipements()->attach($piscine);

    $cocody = publie(['capacite_maximale' => 2], 25000);
    $zone4 = publie(['residence_id' => $marcory->id, 'capacite_maximale' => 6, 'duree_minimale' => 3], 60000);
    $zone4->equipements()->attach($wifi);
    $dates = ['arrivee' => '2026-11-10', 'depart' => '2026-11-14'];

    expect(chercher([...$dates, 'commune_id' => $marcory->quartier->commune_id]))->toBe([$zone4->reference])
        ->and(chercher([...$dates, 'quartier_id' => $cocody->residence->quartier_id]))->toBe([$cocody->reference])
        ->and(chercher([...$dates, 'adultes' => 3, 'enfants' => 2]))->toBe([$zone4->reference])
        ->and(chercher([...$dates, 'budget_max' => 30000]))->toBe([$cocody->reference])
        // Équipement de la RÉSIDENCE et équipement du LOGEMENT : les deux sont exigés.
        ->and(chercher([...$dates, 'equipements' => [$piscine->id, $wifi->id]]))->toBe([$zone4->reference])
        ->and(chercher([...$dates, 'equipements' => [$piscine->id], 'budget_max' => 30000]))->toBe([])
        // 2 nuits : sous la durée minimale de 3 nuits du logement de Zone 4.
        ->and(chercher(['arrivee' => '2026-11-10', 'depart' => '2026-11-12']))->toBe([$cocody->reference]);
});

it('sans dates, montre ce qui est libre ce soir, rangé par commune puis quartier', function (): void {
    $zone4 = publie(['residence_id' => Residence::factory()->create(['quartier_id' => ResidenceFactory::unQuartier('Marcory', 'Zone 4')->id])]);
    $angre = publie();
    $occupeCeSoir = publie();
    sejourner($occupeCeSoir, '2026-09-30', '2026-10-03');

    $reponse = test()->getJson('/api/v1/catalogue/recherche')->assertOk();

    expect($reponse->json('data.avec_dates'))->toBeFalse()
        ->and($reponse->json('data.arrivee'))->toBe('2026-10-01')
        ->and(array_column($reponse->json('data.elements'), 'reference'))->toBe([$angre->reference, $zone4->reference]);
});

it('affiche sur la vignette le prix du séjour demandé, et rien de confidentiel', function (): void {
    $logement = publie();
    $logement->forceFill(['prix_proprietaire' => 21987])->save();

    $reponse = test()->getJson('/api/v1/catalogue/recherche?arrivee=2026-11-10&depart=2026-11-13')->assertOk();

    expect($reponse->json('data.elements.0'))->toMatchArray(['prix_par_nuit' => 30000, 'resume' => 'Appartement 3 pièces, 2 chambres', 'lieu' => ['commune' => 'Cocody', 'quartier' => 'Angré']])
        ->and($reponse->getContent())->not->toContain('21987')->not->toContain('proprietaire')->not->toContain('adresse');
});

it('refuse une recherche incohérente', function (array $criteres): void {
    test()->getJson('/api/v1/catalogue/recherche?'.http_build_query($criteres))->assertStatus(422);
})->with([
    'arrivée sans départ' => [['arrivee' => '2026-11-10']],
    'départ avant l’arrivée' => [['arrivee' => '2026-11-10', 'depart' => '2026-11-09']],
    'dates passées' => [['arrivee' => '2026-09-01', 'depart' => '2026-09-05']],
]);

it('signale dans l’estimation si les dates sont encore libres', function (): void {
    $logement = publie();
    sejourner($logement, '2026-11-10', '2026-11-13');
    $adresse = "/api/v1/catalogue/logements/{$logement->reference}/estimation";

    test()->postJson($adresse, ['arrivee' => '2026-11-11', 'depart' => '2026-11-14', 'adultes' => 2])->assertOk()->assertJsonPath('data.disponible', false);
    test()->postJson($adresse, ['arrivee' => '2026-11-13', 'depart' => '2026-11-16', 'adultes' => 2])->assertOk()->assertJsonPath('data.disponible', true);
});

// ---------------------------------------------------------------- bouton « Occupée / Disponible »

it('ferme une résidence : elle quitte le site, ses séjours confirmés restent à honorer, tout est historisé', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));
    $logement = publie();
    $gestionnaire->residences()->attach($logement->residence_id);
    sejourner($logement, '2026-11-10', '2026-11-13');
    $adresse = "/api/v1/backoffice/residences/{$logement->residence_id}/disponibilite";

    test()->putJson($adresse, ['disponibilite' => 'occupee', 'reouverture_prevue_le' => '2026-12-01', 'motif' => 'Travaux'])
        ->assertOk()->assertJsonPath('data.sejours_a_honorer', 1)->assertJsonPath('data.reouverture_prevue_le', '01/12/2026');

    expect(chercher(['arrivee' => '2026-11-20', 'depart' => '2026-11-22']))->toBe([])
        ->and(DB::table('fermetures_residence')->whereNull('rouverte_le')->count())->toBe(1);

    test()->putJson($adresse, ['disponibilite' => 'disponible'])->assertOk();

    expect(chercher(['arrivee' => '2026-11-20', 'depart' => '2026-11-22']))->toBe([$logement->reference])
        ->and(DB::table('fermetures_residence')->whereNotNull('rouverte_le')->count())->toBe(1);
});
