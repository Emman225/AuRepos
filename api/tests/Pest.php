<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\Cautions;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
| Les tests Feature démarrent l'application. Ceux qui touchent la base
| déclarent eux-mêmes `uses(RefreshDatabase::class)` : ils tournent sur la
| base PostgreSQL `residences_test` (voir phpunit.xml), jamais sur la base
| de développement — Mon Gravier testait sur sa base de travail, et ses
| essais n'étaient ni reproductibles ni lançables en intégration continue.
*/

pest()->extend(TestCase::class)->in('Feature');

/*
| Un règlement déjà EFFECTUÉ, posé directement en base pour un test qui s'intéresse à ce que
| l'argent produit en aval (états, grands livres, retenues) et non au circuit qui l'a amené.
|
| Quatre contraintes PostgreSQL (migration 2026_09_21_001200_create_caisse_tables, révisée par
| 2026_09_22_000100_create_recus_et_avances) refusent un « effectué » bâclé, en SQLSTATE[23514] :
| les étapes ne se sautent pas, la validation revient à un AUTRE que celui qui a saisi, la preuve
| à un TROISIÈME, et la finalisation à celui-là même qui a joint la preuve. Ce raccourci pose
| donc trois personnes réellement distinctes, `finalise_par = preuve_par` compris : il respecte
| la séparation des tâches au lieu de la contourner, les contraintes restant le garde-fou.
|
| Pour tester le circuit LUI-MÊME (validation, preuve, finalisation), passer par
| `Caisse::saisirUnEncaissement()` et ses étapes, jamais par ce raccourci.
|
| @param  array<string, mixed>  $attributs
*/
function reglementEffectue(array $attributs): Reglement
{
    $saisiLe = $attributs['saisi_le'] ?? now();
    $valideur = User::factory()->create()->id;
    $preuve = User::factory()->create()->id; // celui qui joint la preuve finalise aussi

    return Reglement::create([
        'valide_par' => $valideur, 'valide_le' => $saisiLe,
        'preuve_par' => $preuve, 'preuve_le' => $saisiLe, 'preuve_chemin' => 'preuves/test.pdf',
        'finalise_par' => $preuve, 'finalise_le' => $saisiLe,
        ...$attributs,
        'etat' => 'effectue',
    ]);
}

/*
| Dépose ET finalise la caution d'un séjour, circuit de preuve complet (guichet Cautions).
|
| Depuis P2-CAU-01, `CheckIn::effectuer()` exige « soldé ET caution encaissée » (CdC § 6.3) :
| tout test dont le scénario passe par un check-in doit donc avoir déposé la caution, sans quoi
| il échoue en `caution_non_encaissee`. Sans objet si le séjour n'a pas de caution.
*/
function deposerEtFinaliserLaCaution(Sejour $sejour, User $caissier, User $valideur, User $finaliseur): void
{
    if ($sejour->refresh()->caution <= 0) {
        return;
    }

    $caisse = app(Caisse::class);
    $depot = app(Cautions::class)->deposer($caissier, $sejour, $sejour->caution, ModeDeReglement::Especes, 'Caution déposée au guichet');
    $caisse->valider($depot, $valideur);
    $caisse->joindreLaPreuve($depot->refresh(), $finaliseur, UploadedFile::fake()->create('recu-caution.pdf', 10, 'application/pdf'));
    $caisse->finaliser($depot->refresh(), $finaliseur);
}
