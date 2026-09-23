<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Caisse\Services\Cautions;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Check-in (P2-SEJ-01, CdC § 6.3) : l'agent de terrain SAISIT le code d'arrivée que le client
 * détient — il ne le lit jamais (CdC § 11), même mécanisme que
 * `GestionDesTransferts::cloturerParCode`. Le séjour doit être SOLDÉ ET la caution encaissée
 * (CdC § 6.3, « conditions soldé + caution encaissée ») — la seconde condition, longtemps non
 * vérifiable faute de guichet Cautions, l'est désormais via `Cautions::estEncaissee` (P2-CAU-01).
 */
final class CheckIn
{
    public function __construct(
        private readonly CodesSecrets $codes,
        private readonly CycleDuSejour $cycle,
        private readonly SoldeDesSejours $soldes,
        private readonly Cautions $cautions,
    ) {}

    /**
     * @param  list<array{nom: string, prenoms?: string|null, enfant?: bool, type_piece?: string|null, numero_piece?: string|null, telephone?: string|null}>  $occupants  fiche de police : occupants à AJOUTER à ceux déjà connus
     */
    public function effectuer(Sejour $sejour, string $codeSaisi, User $agent, array $occupants = []): Sejour
    {
        if ($sejour->etat !== EtatDuSejour::Confirme) {
            throw new ErreurMetier('Seul un séjour confirmé peut faire l’objet d’un check-in.', 'checkin_impossible', 422);
        }
        if (Carbon::today()->lt($sejour->arrivee)) {
            throw new ErreurMetier('Le check-in n’est possible qu’à partir du jour d’arrivée.', 'checkin_trop_tot', 422);
        }
        if (! $this->soldes->de($sejour)['solde']) {
            throw new ErreurMetier('Ce séjour n’est pas encore soldé : le check-in n’est pas possible.', 'sejour_non_solde', 422);
        }
        // « soldé + caution encaissée » (CdC § 6.3) : sans objet si le séjour n'a pas de caution (Cautions::estEncaissee le rend alors vrai).
        if (! $this->cautions->estEncaissee($sejour)) {
            throw new ErreurMetier('La caution de ce séjour n’est pas encore encaissée : le check-in n’est pas possible.', 'caution_non_encaissee', 422);
        }

        // Ni le code lu, ni le résultat détaillé : juste « oui » ou une erreur reconnaissable (CdC § 11).
        $this->codes->verifier($sejour, ConfirmationDeSejour::CODE_D_ARRIVEE, $codeSaisi, $agent);

        return DB::transaction(function () use ($sejour, $agent, $occupants): Sejour {
            $this->cycle->passer($sejour, EtatDuSejour::Arrive, $agent, [
                'arrive_le' => now(),
                // L'agent qui accueille RÉELLEMENT devient l'agent d'accueil, même si un autre était assigné à la confirmation.
                'agent_accueil_id' => $agent->id,
            ]);

            foreach ($occupants as $occupant) {
                $sejour->occupants()->create($occupant);
            }

            return $sejour->refresh();
        });
    }
}
