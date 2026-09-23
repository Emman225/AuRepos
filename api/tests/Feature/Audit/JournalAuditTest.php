<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function enTantQue(Profil $profil): User
{
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('trace une création, avec son auteur', function (): void {
    $admin = enTantQue(Profil::Administrateur);

    $agence = Agence::create(['nom' => 'Agence Marcory']);

    $entree = EntreeAudit::where('sujet_type', 'Agence')->sole();
    expect($entree->action)->toBe('creation')
        ->and($entree->user_id)->toBe($admin->id)
        ->and($entree->sujet_id)->toBe($agence->id)
        ->and($entree->recit)->toBe('Création : agence « Agence Marcory ».')
        ->and($entree->apres['nom'])->toBe('Agence Marcory');
});

it('trace une modification avec l’avant et l’après des seuls champs changés', function (): void {
    $compte = User::factory()->create(['nom' => 'Koné']);

    $compte->update(['statut' => StatutCompte::Bloque]);

    $entree = EntreeAudit::where('action', 'modification')->sole();
    expect($entree->avant)->toBe(['statut' => 'actif'])
        ->and($entree->apres)->toBe(['statut' => 'bloque'])
        ->and($entree->recit)->toContain('statut');
});

it('ne laisse jamais entrer un mot de passe dans le journal, même haché', function (): void {
    $compte = User::factory()->create();

    $compte->update(['password' => 'NouveauSecret2026']);

    $journal = EntreeAudit::all()->toJson();
    expect($journal)->not->toContain('NouveauSecret2026')->not->toContain('$2y$')
        ->and(EntreeAudit::where('action', 'modification')->sole()->apres['password'])->toBe('••• (masqué)');
});

it('ne trace pas une modification purement technique', function (): void {
    $compte = User::factory()->create();

    $compte->update(['derniere_connexion_le' => now()]);

    expect(EntreeAudit::where('action', 'modification')->count())->toBe(0);
});

it('trace les connexions réussies et les mots de passe refusés', function (): void {
    User::factory()->create(['email' => 'awa@exemple.ci']);

    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'faux']);
    test()->postJson('/api/v1/auth/connexion', ['identifiant' => 'awa@exemple.ci', 'mot_de_passe' => 'password']);

    expect(EntreeAudit::where('action', 'connexion_refusee')->count())->toBe(1)
        ->and(EntreeAudit::where('action', 'connexion')->count())->toBe(1)
        ->and(EntreeAudit::where('action', 'connexion')->sole()->ip)->not->toBeNull();
});

it('est inaltérable : la base refuse de modifier ou d’effacer une ligne', function (): void {
    Agence::create(['nom' => 'Agence Yopougon']);
    $id = EntreeAudit::firstOrFail()->id;

    expect(fn () => DB::transaction(fn () => DB::table('journal_audit')->where('id', $id)->update(['recit' => 'falsifié'])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('journal_audit')->where('id', $id)->delete()))
        ->toThrow(QueryException::class);

    expect(EntreeAudit::findOrFail($id)->recit)->not->toBe('falsifié');
});

it('ne fait jamais échouer l’opération tracée quand le journal est en panne', function (): void {
    // On casse le journal : sa table disparaît le temps de l'essai.
    DB::statement('ALTER TABLE journal_audit RENAME TO journal_audit_casse');
    try {
        // L'opération métier aboutit quand même, et la transaction PostgreSQL en cours
        // reste utilisable (l'écriture ratée est isolée par un point de sauvegarde).
        $agence = Agence::create(['nom' => 'Agence créée malgré la panne']);
        app(JournalAudit::class)->consigner('essai', 'Ne doit pas lever d’exception.');
    } finally {
        DB::statement('ALTER TABLE journal_audit_casse RENAME TO journal_audit');
    }

    expect(Agence::whereKey($agence->id)->exists())->toBeTrue();
});

it('réserve la consultation du journal aux administrateurs', function (Profil $profil, int $statut): void {
    enTantQue($profil);

    test()->getJson('/api/v1/backoffice/audit')->assertStatus($statut);
})->with([
    [Profil::SuperAdministrateur, 200], [Profil::Administrateur, 200],
    [Profil::Gestionnaire, 403], [Profil::Proprietaire, 403], [Profil::Client, 403],
]);

it('liste le journal du plus récent au plus ancien, paginé, au format des listes', function (): void {
    enTantQue(Profil::Administrateur);
    Agence::create(['nom' => 'Agence A']);
    Agence::create(['nom' => 'Agence B']);

    $reponse = test()->getJson('/api/v1/backoffice/audit?sujet_type=Agence&par_page=5')->assertOk();

    expect($reponse->json('data.pagination.total'))->toBe(2)
        ->and($reponse->json('data.elements.0.recit'))->toContain('Agence B')
        ->and($reponse->json('data.elements.0.date'))->toMatch('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#');
});

it('filtre par période, bornes incluses, et refuse une période à l’envers', function (): void {
    enTantQue(Profil::Administrateur);

    test()->travelTo('2026-09-10 23:30:00');
    Agence::create(['nom' => 'Agence du 10']);
    test()->travelTo('2026-09-15 08:00:00');
    Agence::create(['nom' => 'Agence du 15']);
    test()->travelBack();

    $dansLaPeriode = test()->getJson('/api/v1/backoffice/audit?sujet_type=Agence&du=10/09/2026&au=10/09/2026')->assertOk();
    expect($dansLaPeriode->json('data.pagination.total'))->toBe(1)
        ->and($dansLaPeriode->json('data.elements.0.recit'))->toContain('Agence du 10');

    test()->getJson('/api/v1/backoffice/audit?du=2026-09-20&au=2026-09-01')->assertStatus(422);
    test()->getJson('/api/v1/backoffice/audit?du=pas-une-date')->assertStatus(422);
});

it('garde le même type avant et après pour un champ JSON', function (): void {
    $super = enTantQue(Profil::SuperAdministrateur);
    $parametres = app(Parametres::class);

    $parametres->enregistrer('taxes', ['sejour_montant' => 500], $super);
    $parametres->enregistrer('taxes', ['sejour_montant' => 0], $super);

    $entree = EntreeAudit::where('action', 'modification')->where('sujet_type', 'Parametre')->sole();
    expect($entree->avant)->toBe(['valeur' => 500])->and($entree->apres)->toBe(['valeur' => 0]);
});
