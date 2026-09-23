<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Comptes\Services\InscriptionService;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Partenaires\Services\Parrainage;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    $this->agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $this->agence->id]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    [$this->a1, $this->a2, $this->a3] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
});

/** Encaisse un montant sur un séjour, jusqu'au bout du circuit de preuve — la commission naît à la finalisation. */
function encaisserPourLeSejour(Sejour $sejour, int $montant): Reglement
{
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement(test()->caissier, $sejour->client, [$sejour->id], $montant, ModeDeReglement::Especes, 'Versement');
    $caisse->valider($r, test()->a1);
    $caisse->joindreLaPreuve($r->refresh(), test()->a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), test()->a2);

    return $r->refresh();
}

// ---------------------------------------------------------------- parrainage à l'inscription

it('rattache le client à l’apporteur dont il donne le code', function (): void {
    $apporteur = Apporteur::factory()->create(['pourcentage' => 15]);

    $client = app(InscriptionService::class)->inscrire([
        'nom' => 'Koffi', 'email' => 'koffi@exemple.ci', 'mot_de_passe' => 'Motdepasse!23', 'code_parrain' => $apporteur->code,
    ]);

    expect($client->refresh()->parraine_par_id)->toBe($apporteur->id);
});

it('inscrit normalement un client sans code de parrainage', function (): void {
    $client = app(InscriptionService::class)->inscrire([
        'nom' => 'Koffi', 'email' => 'sanscode@exemple.ci', 'mot_de_passe' => 'Motdepasse!23',
    ]);

    expect($client->refresh()->parraine_par_id)->toBeNull();
});

it('refuse un code de parrainage inconnu, et ne crée alors aucun compte', function (): void {
    expect(fn () => app(InscriptionService::class)->inscrire([
        'nom' => 'Koffi', 'email' => 'refuse@exemple.ci', 'mot_de_passe' => 'Motdepasse!23', 'code_parrain' => 'INCONNU',
    ]))->toThrow(ErreurMetier::class);

    expect(User::where('email', 'refuse@exemple.ci')->exists())->toBeFalse();
});

it('refuse le code d’un apporteur désactivé', function (): void {
    $apporteur = Apporteur::factory()->inactif()->create();

    expect(fn () => app(Parrainage::class)->inscrireAvecCode(User::factory()->create(), $apporteur->code))
        ->toThrow(ErreurMetier::class);
});

it('génère un code unique à la création si aucun n’est fourni', function (): void {
    $a1 = Apporteur::factory()->create();
    $a2 = Apporteur::factory()->create();

    expect($a1->code)->not->toBeEmpty()->and($a2->code)->not->toBe($a1->code);
});

// ---------------------------------------------------------------- commission par tranche

it('crée une commission à CHAQUE règlement encaissé — jamais sur le total', function (): void {
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 100000]);

    // Première tranche.
    $r1 = encaisserPourLeSejour($sejour, 30000);
    // Seconde tranche, quelques jours plus tard.
    $r2 = encaisserPourLeSejour($sejour, 70000);

    $commissions = CommissionApporteur::where('apporteur_id', $apporteur->id)->orderBy('id')->get();
    expect($commissions)->toHaveCount(2)
        ->and($commissions[0]->reglement_id)->toBe($r1->id)
        ->and($commissions[0]->montant)->toBe(3000) // 10 % de 30 000, jamais 10 % des 100 000 du séjour
        ->and($commissions[1]->reglement_id)->toBe($r2->id)
        ->and($commissions[1]->montant)->toBe(7000);
});

it('ne crée aucune commission si le client n’a pas de parrain', function (): void {
    $client = User::factory()->create(); // parraine_par_id = null
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 50000]);

    encaisserPourLeSejour($sejour, 50000);

    expect(CommissionApporteur::count())->toBe(0);
});

it('ne crée aucune commission quand l’apporteur a été désactivé entre-temps', function (): void {
    $apporteur = Apporteur::factory()->inactif()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 50000]);

    encaisserPourLeSejour($sejour, 50000);

    expect(CommissionApporteur::count())->toBe(0);
});

it('ne crée jamais de commission sur un décaissement', function (): void {
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 50000]);
    encaisserPourLeSejour($sejour, 50000);
    expect(CommissionApporteur::count())->toBe(1);

    // Un décaissement (reversement) vers un tiers quelconque ne doit jamais générer de commission.
    $caisse = app(Caisse::class);
    $decaissement = $caisse->saisirUnDecaissement(test()->a1, $apporteur->utilisateur, 3000, ModeDeReglement::Especes, 'Reversement');
    $caisse->valider($decaissement, test()->a2);
    $caisse->joindreLaPreuve($decaissement->refresh(), test()->a3, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($decaissement->refresh(), test()->a3);

    expect(CommissionApporteur::count())->toBe(1);
});

it('est idempotent : rejouer l’attribution pour le même règlement ne recrée pas de commission', function (): void {
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 50000]);
    $reglement = encaisserPourLeSejour($sejour, 50000);
    expect(CommissionApporteur::count())->toBe(1);

    // Rejoue directement le service, comme un événement dupliqué le ferait.
    app(Parrainage::class)->attribuerLaCommission($reglement->refresh());

    expect(CommissionApporteur::count())->toBe(1);
});

// ---------------------------------------------------------------- solde dû

it('calcule le solde dû comme les commissions cumulées moins les décaissements déjà versés', function (): void {
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 100000]);
    encaisserPourLeSejour($sejour, 30000); // commission 3 000
    encaisserPourLeSejour($sejour, 70000); // commission 7 000 → 10 000 cumulés

    $parrainage = app(Parrainage::class);
    expect($parrainage->soldeDu($apporteur))->toBe(10000);

    $caisse = app(Caisse::class);
    $decaissement = $caisse->saisirUnDecaissement(test()->a1, $apporteur->utilisateur, 4000, ModeDeReglement::Especes, 'Reversement partiel');
    $caisse->valider($decaissement, test()->a2);
    $caisse->joindreLaPreuve($decaissement->refresh(), test()->a3, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($decaissement->refresh(), test()->a3);

    expect($parrainage->soldeDu($apporteur))->toBe(6000);
});
