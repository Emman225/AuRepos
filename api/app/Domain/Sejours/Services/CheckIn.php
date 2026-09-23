<?php

namespace App\Domain\Sejours\Services;

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
 * `GestionDesTransferts::cloturerParCode`. Le séjour doit être SOLDÉ (CdC § 6.3, « conditions
 * soldé + caution encaissée ») ; la caution, elle, n'a pas encore son propre guichet dans ce
 * projet (prévu pour un lot ultérieur, cf. commentaire de `Guichet::prefixeDuRecu`) — cette
 * seconde condition n'est donc pas vérifiable ici et n'est délibérément PAS posée en dur.
 */
final class CheckIn
{
    public function __construct(
        private readonly CodesSecrets $codes,
        private readonly CycleDuSejour $cycle,
        private readonly SoldeDesSejours $soldes,
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
