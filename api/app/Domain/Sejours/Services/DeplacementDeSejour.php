<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Déplacement d'un séjour vers un autre logement DU MÊME TYPE (P2-PLA-02, CdC § 6.2) : le
 * planning back-office permet de reloger un client sans annuler puis recréer le séjour — utile
 * en cas de panne, de sur-réservation d'un canal externe ou de réaffectation d'exploitation.
 *
 * Toujours motivé (traçabilité). La disponibilité du logement de destination est REVÉRIFIÉE
 * au moment même de l'écriture, MÊME contrainte d'exclusion PostgreSQL que toute réservation
 * (Calendrier::occuper — « on ne vérifie pas PUIS on écrit », cf. sa documentation) : deux
 * déplacements simultanés vers le même logement ne peuvent pas tous les deux réussir.
 */
final class DeplacementDeSejour
{
    public function __construct(
        private readonly Calendrier $calendrier,
        private readonly JournalAudit $journal,
    ) {}

    public function deplacer(Sejour $sejour, Logement $destination, string $motif, User $auteur): Sejour
    {
        if (! $sejour->etat->occupeLeCalendrier() || $sejour->etat === EtatDuSejour::Cloture) {
            throw new ErreurMetier(
                'Seul un séjour en cours (demandé, confirmé, arrivé ou parti) peut être déplacé.',
                'sejour_non_deplacable',
                422,
            );
        }
        if ($destination->id === $sejour->logement_id) {
            throw new ErreurMetier('Ce séjour est déjà dans ce logement.', 'logement_inchange', 422);
        }
        if ($destination->type_logement_id !== $sejour->logement->type_logement_id) {
            throw new ErreurMetier('Le déplacement n’est possible que vers un logement du même type.', 'type_logement_different', 422);
        }
        if (trim($motif) === '') {
            throw new ErreurMetier('Un déplacement doit toujours être motivé.', 'motif_obligatoire', 422);
        }

        return DB::transaction(function () use ($sejour, $destination, $motif, $auteur): Sejour {
            $ancienLogement = $sejour->logement;
            $motif = trim($motif);

            $sejour->forceFill(['logement_id' => $destination->id])->saveQuietly();

            // Revérifie la disponibilité MAINTENANT : Calendrier::ecrire() retire l'ancienne
            // occupation (clé sejour_id) et en pose une nouvelle sur le logement de destination ;
            // la contrainte d'exclusion PostgreSQL refuse si une autre occupation y tient déjà ces dates.
            $this->calendrier->occuper($sejour);

            $this->journal->consigner(
                'sejour_deplace',
                $sejour->libelleAudit().' : déplacé de « '.$ancienLogement->nom.' » vers « '.$destination->nom.' ». Motif : '.$motif.'.',
                $sejour,
                ['logement_id' => $ancienLogement->id, 'logement' => $ancienLogement->nom],
                ['logement_id' => $destination->id, 'logement' => $destination->nom, 'motif' => $motif],
                $auteur,
            );

            return $sejour->refresh();
        });
    }
}
