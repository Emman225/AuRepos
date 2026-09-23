<?php

namespace App\Domain\Assistance\Services;

use App\Domain\Assistance\Enums\EtatDeLaReclamation;
use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Validation\Models\ChangementAValider;
use App\Domain\Validation\Services\DoubleValidation;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Réclamations (P2-AST-01, CdC § 6.1) : un client en soulève une APRÈS un séjour (parti ou
 * clôturé), motif d'AU MOINS 15 CARACTÈRES (CdC, exact — pas arrondi). Se ferme sans rien,
 * ou avec un avoir / geste commercial.
 *
 * L'avoir reprend EXACTEMENT le mécanisme de `ReductionSurSejour` : un administrateur
 * propose un MONTANT SAISI À LA MAIN (même précédent que la retenue sur caution ou la
 * rémunération d'un chauffeur — jamais une formule) sur `avoir_montant`, via la double
 * validation générique (`DoubleValidation`) ; LE trésorier désigné dans les Paramètres
 * (`Parametres::tresorierDesigne()`, `gestionnaires.validant_2_id`) confirme, jamais un
 * autre administrateur.
 *
 * Le versement passe par `Caisse::saisirUnDecaissement` (même circuit qu'un remboursement
 * d'annulation, `DemandesDAnnulation`), PAS par `Factures::emettreUnAvoir` : celui-ci
 * n'annule qu'une facture ENTIÈRE (jamais une part), et exige une facture déjà TRANSMISE —
 * un geste commercial est un montant saisi à la main, presque toujours partiel, et la
 * plupart des séjours n'ont encore qu'une proforma à ce stade. Un administrateur émet un
 * avoir DGI à la main, depuis l'écran Factures déjà existant, si une vraie facture
 * transmise doit vraiment être annulée (même choix documenté par `DemandesDAnnulation`).
 */
final class Reclamations
{
    public const CHAMP_AVOIR = 'avoir_montant';

    private const MOTIF_MIN = 15;

    public function __construct(
        private readonly DoubleValidation $doubleValidation,
        private readonly Parametres $parametres,
        private readonly Caisse $caisse,
    ) {}

    public function creer(Sejour $sejour, User $client, string $motif): Reclamation
    {
        if (! in_array($sejour->etat, [EtatDuSejour::Parti, EtatDuSejour::Cloture], true)) {
            throw new ErreurMetier('Une réclamation ne se soulève qu’après un séjour terminé.', 'sejour_non_termine', 422);
        }
        if (mb_strlen(trim($motif)) < self::MOTIF_MIN) {
            throw new ErreurMetier(
                'Le motif doit compter au moins '.self::MOTIF_MIN.' caractères.', 'motif_trop_court', 422,
            );
        }

        return Reclamation::create(['sejour_id' => $sejour->id, 'client_id' => $client->id, 'motif' => trim($motif)])->refresh();
    }

    /** Fermeture sans avoir : une simple réponse au client. */
    public function fermer(Reclamation $reclamation, User $administrateur, ?string $reponse): Reclamation
    {
        $this->exigerPasFermee($reclamation);

        $reclamation->update([
            'statut' => EtatDeLaReclamation::Fermee, 'reponse' => $reponse ? trim($reponse) : $reclamation->reponse,
            'fermee_par' => $administrateur->id, 'fermee_le' => now(),
        ]);

        return $reclamation->refresh();
    }

    /** Proposition d'avoir / geste commercial : un administrateur saisit un montant motivé. */
    public function proposerUnAvoir(Reclamation $reclamation, int $montant, string $motif, User $administrateur): ChangementAValider
    {
        $this->exigerPasFermee($reclamation);

        if (! $this->parametres->tresorierDesigne()) {
            throw new ErreurMetier('Aucun trésorier n’est désigné dans les Paramètres : aucun avoir n’est possible.', 'tresorier_non_designe', 422);
        }
        if ($montant <= 0) {
            throw new ErreurMetier('Le montant de l’avoir doit être positif.', 'montant_invalide', 422);
        }

        $changement = $this->doubleValidation->proposer($reclamation, self::CHAMP_AVOIR, $montant, $administrateur, $motif);
        $reclamation->forceFill(['avoir_motif' => trim($motif), 'statut' => EtatDeLaReclamation::EnCours])->saveQuietly();

        return $changement;
    }

    /** Seul LE trésorier désigné confirme — pas « un autre administrateur » (CdC § 6.1). */
    public function confirmerAvoir(ChangementAValider $changement, User $tresorier, ?ModeDeReglement $mode): Reclamation
    {
        $designe = $this->parametres->valeur('gestionnaires.validant_2_id');
        if ($designe === null || (int) $designe !== $tresorier->id) {
            throw new ErreurMetier('Seul le trésorier désigné dans les Paramètres peut confirmer un avoir.', 'tresorier_requis', 403);
        }
        if ($mode === null) {
            throw new ErreurMetier('Indiquez le mode de règlement de l’avoir.', 'mode_de_remboursement_obligatoire', 422);
        }

        return DB::transaction(function () use ($changement, $tresorier, $mode): Reclamation {
            $this->doubleValidation->valider($changement, $tresorier);
            /** @var Reclamation $reclamation */
            $reclamation = $changement->sujet->refresh();

            $reglement = $this->caisse->saisirUnDecaissement(
                $tresorier, $reclamation->client, (int) $reclamation->avoir_montant, $mode,
                'Avoir / geste commercial sur réclamation — '.$reclamation->sejour->libelleAudit(),
                Guichet::Remboursements,
            );

            $reclamation->update([
                'statut' => EtatDeLaReclamation::Fermee, 'reglement_id' => $reglement->id,
                'fermee_par' => $tresorier->id, 'fermee_le' => now(),
            ]);

            return $reclamation->refresh();
        });
    }

    private function exigerPasFermee(Reclamation $reclamation): void
    {
        if ($reclamation->fermee()) {
            throw new ErreurMetier('Cette réclamation est déjà fermée.', 'reclamation_deja_fermee', 422);
        }
    }
}
