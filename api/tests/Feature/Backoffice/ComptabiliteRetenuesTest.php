<?php

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\NatureJuridique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

it('applique le taux de retenue à la source (personne physique) au décaissement effectué vers un propriétaire', function (): void {
    $proprietaireUser = User::factory()->profil(Profil::Proprietaire)->create();
    Proprietaire::factory()->create(['user_id' => $proprietaireUser->id, 'nature' => NatureJuridique::PersonnePhysique, 'interne' => false]);

    reglementEffectue([
        'sens' => 'decaissement', 'guichet' => 'dettes_partenaires', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => $proprietaireUser->id, 'montant' => 100000, 'mode' => 'virement', 'notes' => 'Reversement',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => '2026-11-05 10:00:00',
    ]);

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    // Taux par défaut : 7,5 % pour une personne physique (config/parametres.php « taxes.retenue_personne_physique »).
    test()->getJson('/api/v1/backoffice/comptabilite/retenues?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.montant_brut', 100000)
        ->assertJsonPath('data.totaux.retenue', 7500)
        ->assertJsonPath('data.totaux.net_verse', 92500);
});

it('ne retient rien sur le reversement d’un compte propriétaire interne', function (): void {
    $proprietaireUser = User::factory()->profil(Profil::Proprietaire)->create();
    Proprietaire::factory()->interne()->create(['user_id' => $proprietaireUser->id]);

    reglementEffectue([
        'sens' => 'decaissement', 'guichet' => 'dettes_partenaires', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => $proprietaireUser->id, 'montant' => 50000, 'mode' => 'virement', 'notes' => 'Reversement interne',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => '2026-11-05 10:00:00',
    ]);

    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    test()->getJson('/api/v1/backoffice/comptabilite/retenues?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.retenue', 0)
        ->assertJsonPath('data.totaux.net_verse', 50000);
});
