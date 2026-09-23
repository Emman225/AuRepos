<?php

use App\Domain\Audit\Models\EntreeAudit;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\Cautions;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Services\ConfirmationDeSejour;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    Mail::fake();
    Storage::fake('local');

    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);
    $this->gestionnaire = $personnel(Profil::Gestionnaire);
    $this->admins = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->agent = User::factory()->profil(Profil::AgentTerrain)->create();

    $residence = Residence::factory()->create(['adresse' => 'Rue des Jardins, lot 45']);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();

    $this->client = User::factory()->create(['email' => 'awa@exemple.ci']);
    $this->sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
    ]);

    // Payé intégralement : « soldé » (CdC § 6.3).
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$this->sejour->id], $this->sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);

    // Caution déposée et finalisée : seconde condition du check-in (CdC § 6.3, P2-CAU-01).
    deposerEtFinaliserLaCaution($this->sejour);

    app(ConfirmationDeSejour::class)->confirmer($this->sejour->refresh(), $this->gestionnaire);
    $this->sejour->refresh();
    $this->code = app(CodesSecrets::class)->lirePourLeClient($this->sejour, 'arrivee');
});

/** Circuit complet du dépôt de caution (même schéma que le règlement du séjour, guichet Cautions). */
function deposerEtFinaliserLaCaution(\App\Domain\Sejours\Models\Sejour $sejour): void
{
    $caisse = app(Caisse::class);

    $r = app(Cautions::class)->deposer(test()->gestionnaire, $sejour, $sejour->refresh()->caution, ModeDeReglement::Especes, 'Caution déposée au guichet');
    $caisse->valider($r, test()->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), test()->admins[1], UploadedFile::fake()->create('recu-caution.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), test()->admins[1]);
}

function connecteAgent(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

it('refuse le check-in tant que le séjour n’est pas soldé', function (): void {
    // Un second séjour, non payé.
    $logement = Logement::factory()->create(['residence_id' => $this->sejour->logement->residence_id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-12', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);
    // Acompte seulement (pas le solde intégral), puis confirmé.
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$sejour->id], $sejour->acompte_exige, ModeDeReglement::Especes, 'Acompte');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);
    app(ConfirmationDeSejour::class)->confirmer($sejour->refresh(), $this->gestionnaire);
    $code = app(CodesSecrets::class)->lirePourLeClient($sejour->refresh(), 'arrivee');

    connecteAgent($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$sejour->id}/check-in", ['code' => $code])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_solde');

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);
});

it('refuse le check-in tant que la caution n’est pas encaissée, même le séjour soldé (P2-CAU-01)', function (): void {
    // Un second séjour, soldé intégralement, mais SANS dépôt de caution.
    $logement = Logement::factory()->create(['residence_id' => $this->sejour->logement->residence_id]);
    $logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $sejour = app(ReservationDeSejour::class)->reserver($this->client, $logement->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-12', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($this->gestionnaire, $this->client, [$sejour->id], $sejour->net_a_payer, ModeDeReglement::Especes, 'Solde complet');
    $caisse->valider($r, $this->admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $this->admins[1], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $this->admins[1]);
    app(ConfirmationDeSejour::class)->confirmer($sejour->refresh(), $this->gestionnaire);
    $code = app(CodesSecrets::class)->lirePourLeClient($sejour->refresh(), 'arrivee');

    expect($sejour->refresh()->caution)->toBeGreaterThan(0); // la condition n'a de sens que si une caution est due

    connecteAgent($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$sejour->id}/check-in", ['code' => $code])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'caution_non_encaissee');

    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme);

    // Une fois la caution déposée et finalisée, le check-in redevient possible : même séjour, même code.
    deposerEtFinaliserLaCaution($sejour->refresh());

    test()->postJson("/api/v1/agent/sejours/{$sejour->id}/check-in", ['code' => $code])->assertOk();
    expect($sejour->refresh()->etat)->toBe(EtatDuSejour::Arrive);
});

it('effectue le check-in avec le bon code, jamais avant le jour d’arrivée', function (): void {
    Carbon::setTestNow('2026-11-09 09:00:00'); // veille de l'arrivée

    connecteAgent($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $this->code])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'checkin_trop_tot');

    Carbon::setTestNow('2026-11-10 09:00:00'); // jour d'arrivée
    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $this->code])
        ->assertOk()->assertJsonPath('data.etat', 'arrive');

    $sejour = $this->sejour->refresh();
    expect($sejour->etat)->toBe(EtatDuSejour::Arrive)
        ->and($sejour->arrive_le)->not->toBeNull()
        ->and($sejour->agent_accueil_id)->toBe($this->agent->id)
        ->and($reponse->getContent())->not->toContain($this->code); // jamais le code dans la réponse
});

it('crée la fiche de police au check-in, et le numéro de pièce n’en ressort jamais en clair', function (): void {
    connecteAgent($this->agent);
    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", [
        'code' => $this->code,
        'occupants' => [
            ['nom' => 'Kouassi', 'prenoms' => 'Awa', 'type_piece' => 'cni', 'numero_piece' => 'CI0099887766'],
            ['nom' => 'Kouassi', 'prenoms' => 'Junior', 'enfant' => true],
        ],
    ])->assertOk();

    expect($reponse->getContent())->not->toContain('CI0099887766');

    $occupants = $this->sejour->occupants()->get();
    expect($occupants)->toHaveCount(2)
        ->and($occupants->firstWhere('nom', 'Kouassi')?->numero_piece)->toBe('CI0099887766'); // déchiffré via le modèle
    expect(DB::table('occupants')->value('numero_piece'))->not->toContain('CI0099887766');
});

it('dépose la photo de la pièce d’un occupant, chiffrée, jamais un second mécanisme de stockage', function (): void {
    connecteAgent($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", [
        'code' => $this->code, 'occupants' => [['nom' => 'Kouassi', 'prenoms' => 'Awa']],
    ])->assertOk();
    $occupant = $this->sejour->occupants()->sole();

    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/occupants/{$occupant->id}/piece", [
        'fichier' => UploadedFile::fake()->image('cni.jpg', 800, 600)->size(200),
    ])->assertOk();

    expect(DB::table('pieces_justificatives')->where('titulaire_type', $occupant->getMorphClass())->where('titulaire_id', $occupant->id)->count())->toBe(1);
    $chemin = DB::table('pieces_justificatives')->value('chemin');
    Storage::disk('local')->assertExists($chemin);
    // Le contenu écrit sur le disque n'est pas l'image en clair (chiffré).
    expect(Storage::disk('local')->get($chemin))->not->toContain('JFIF');
});

it('vérifie le code par usage « arrivee » : faux cinq fois puis verrouillé, comme un transfert', function (): void {
    connecteAgent($this->agent);
    $faux = $this->code === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $essai) {
        test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $faux])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'code_incorrect');
    }
    // Le cinquième essai (même faux) verrouille.
    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $faux])
        ->assertStatus(423)->assertJsonPath('errors.code.0', 'code_verrouille');

    // Même le BON code ne passe plus.
    $reponse = test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $this->code])
        ->assertStatus(423)->assertJsonPath('errors.code.0', 'code_verrouille');

    expect($this->sejour->refresh()->etat)->toBe(EtatDuSejour::Confirme)
        ->and(EntreeAudit::where('action', 'code_verrouille')->count())->toBe(1);
});

it('refuse un check-in sur un séjour qui n’est pas confirmé', function (): void {
    $this->sejour->update(['etat' => EtatDuSejour::Demande]);

    connecteAgent($this->agent);
    test()->postJson("/api/v1/agent/sejours/{$this->sejour->id}/check-in", ['code' => $this->code])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'checkin_impossible');
});

it('ferme l’espace agent aux autres profils', function (Profil $profil): void {
    connecteAgent(User::factory()->profil($profil)->create());

    test()->getJson('/api/v1/agent/sejours')->assertForbidden();
})->with([Profil::Client, Profil::Chauffeur, Profil::Gestionnaire]);
