<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Exploitation\Models\Mission;
use App\Domain\Maintenance\Enums\EtatDuTicketMaintenance;
use App\Domain\Maintenance\Models\TicketMaintenance;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Agrégation du planning (grille logements × jours, P2-PLA-01/02/03, CdC § 6.2) : construit,
 * à partir des séjours, des missions de ménage, des blocages calendrier et des tickets de
 * maintenance DÉJÀ existants, ce que l'écran back-office affiche — sans nouvelle règle de
 * facturation ni état inventé.
 *
 * Les 8 états colorés du brief de refonte (`web/src/shared/theme/jetons.ts`) se répartissent
 * ainsi : libre / réservé / occupé / départ du jour se lisent sur les séjours déjà renvoyés
 * par `PlanningController::index` (l'écran connaît déjà `EtatDuSejour`, rien de neuf ici) ;
 * EN MÉNAGE sur `missions()` (P2-MEN-01, seul type de mission construit à ce jour — un type
 * futur serait simplement ignoré par l'écran tant qu'il ne sait pas le dessiner) ; EN
 * MAINTENANCE sur `blocages()` (motif `maintenance`) ET sur `ticketsMaintenance()` : un ticket
 * BLOQUANT (P2-MNT-01) pose lui-même un blocage de ce motif — les deux listes le décrivent
 * alors, reliées par `blocage_id` — tandis qu'un ticket ouvert NON bloquant n'a aucune trace
 * calendaire et ne se voit que par la seconde ; BLOQUÉ PROPRIÉTAIRE sur `blocages()` (motif
 * `usage_proprietaire`, CdC § 6.2) ; « Occupée (fermée par le propriétaire) » se lit
 * sur `logement.ferme` (voir PlanningLogementResource), dérivé du bouton « Occupée / Disponible »
 * déjà existant sur la résidence (`Residence::disponibilite`), jamais d'un nouveau champ.
 */
final class Planning
{
    /**
     * Missions de ménage qui échoient dans la période, pour les logements donnés. Défensif :
     * logements vides ou aucune mission encore créée → collection vide, jamais d'erreur.
     *
     * @param  list<int>  $logementIds
     * @return Collection<int, Mission>
     */
    public function missions(array $logementIds, Carbon $du, Carbon $au): Collection
    {
        if ($logementIds === []) {
            return new Collection;
        }

        return Mission::query()
            ->whereIn('logement_id', $logementIds)
            ->whereBetween('echeance', [$du->copy()->startOfDay(), $au->copy()->endOfDay()])
            ->get();
    }

    /**
     * Blocages calendrier (maintenance, usage propriétaire, saison fermée, canal externe) qui
     * chevauchent la période, pour les logements donnés. Le blocage tient [debut, fin] bornes
     * INCLUSES (BlocageCalendrier, Calendrier::bloquer) : le chevauchement se teste donc avec
     * deux `daterange` fermés, contrairement à celui des séjours ([arrivée, départ[).
     *
     * @param  list<int>  $logementIds
     * @return Collection<int, BlocageCalendrier>
     */
    public function blocages(array $logementIds, Carbon $du, Carbon $au): Collection
    {
        if ($logementIds === []) {
            return new Collection;
        }

        return BlocageCalendrier::query()
            ->whereIn('logement_id', $logementIds)
            ->whereRaw('daterange(debut, fin, \'[]\') && daterange(?, ?, \'[]\')', [$du->toDateString(), $au->toDateString()])
            ->get();
    }

    /**
     * Tickets de maintenance OUVERTS qui concernent la période (P2-MNT-01). Complément, et non
     * doublon, des blocages : seul un ticket « bloquant » retire des dates du calendrier (il pose
     * alors un BlocageCalendrier motif « maintenance », déjà renvoyé par blocages() et retrouvable
     * ici par `blocage_id`). Un ticket ouvert NON bloquant — une panne signalée qui n'empêche pas
     * de louer — n'a aucune trace calendaire : sans cette liste, le planning ne pourrait pas le
     * montrer du tout. L'écran décide s'il le dessine en pastille plutôt qu'en barre pleine.
     *
     * Un ticket ouvert APRÈS la fin de la période n'existait pas à l'époque affichée, et un ticket
     * dont l'indisponibilité est déjà levée avant le début ne la concerne plus : les deux sont exclus.
     *
     * @param  list<int>  $logementIds
     * @return Collection<int, TicketMaintenance>
     */
    public function ticketsMaintenance(array $logementIds, Carbon $du, Carbon $au): Collection
    {
        if ($logementIds === []) {
            return new Collection;
        }

        return TicketMaintenance::query()
            ->whereIn('logement_id', $logementIds)
            ->where('statut', EtatDuTicketMaintenance::Ouvert->value)
            ->where('created_at', '<', $au->copy()->addDay()->startOfDay())
            ->where(fn ($q) => $q->whereNull('indisponible_jusquau')->orWhere('indisponible_jusquau', '>=', $du->toDateString()))
            ->get();
    }

    /**
     * Indicateurs en tête de planning (P2-PLA-03, CdC § 6.2) : taux d'occupation, RevPAR, prix
     * moyen — calculés UNIQUEMENT à partir des séjours déjà chargés (mêmes états que la grille,
     * `PlanningController::etatsQuiOccupentLeCalendrier`) et de leur devis FIGÉ, jamais d'une
     * nouvelle source de prix.
     *
     * « Nuits disponibles » = tout le parc (nombre de logements du périmètre × jours de la
     * période) : un blocage (maintenance, propriétaire…) retire une nuit de la VENTE, pas de
     * l'INVENTAIRE — c'est le sens habituel du RevPAR hôtelier, et le seul qui ne mélange pas
     * deux sources de vérité (séjours d'un côté, blocages de l'autre) dans un seul ratio.
     *
     * Un séjour qui ne chevauche la période qu'en partie voit son chiffre d'affaires hébergement
     * (`devis.hebergement_net_ht`, HT et déjà net de remise) PRORATÉ au nombre de nuits
     * réellement dans la période (nuits_periode / nuits_totales_du_séjour). Ventiler nuit par
     * nuit depuis `devis.nuitees` serait plus précis mais s'appuierait sur la forme interne
     * d'un tableau JSON non typée ; le prorata reste entièrement fidèle au devis figé réel et
     * n'invente aucun montant.
     *
     * @param  Collection<int, Sejour>  $sejours  séjours DÉJÀ filtrés : occupent le calendrier, chevauchent [du, au]
     * @return array{periode: array{du: string, au: string, jours: int}, nombre_de_logements: int, nuits_disponibles: int, nuits_occupees: int, taux_occupation: float, ca_hebergement: int, revpar: int, prix_moyen: int}
     */
    public function indicateurs(Collection $sejours, int $nombreDeLogements, Carbon $du, Carbon $au): array
    {
        $jours = (int) $du->diffInDays($au) + 1;
        $nuitsDisponibles = $nombreDeLogements * $jours;
        $finExclue = $au->copy()->addDay();

        $nuitsOccupees = 0;
        $caHebergement = 0.0;

        foreach ($sejours as $sejour) {
            // Même fenêtre [arrivée, départ[ que PlanningController::index, clippée à [du, au].
            $debutClip = $sejour->arrivee->gt($du) ? $sejour->arrivee : $du;
            $finClip = $sejour->depart->lt($finExclue) ? $sejour->depart : $finExclue;
            $nuitsPeriode = max(0, (int) $debutClip->diffInDays($finClip));
            if ($nuitsPeriode === 0) {
                continue;
            }

            $nuitsOccupees += $nuitsPeriode;

            // Défensif : un séjour sans devis figé (donnée de test, séjour créé hors du
            // parcours normal) ne casse rien — il compte dans l'occupation, pas dans le CA.
            $nuitsTotales = $sejour->nombreDeNuits();
            $hebergementNetHt = (float) Arr::get($sejour->devis ?? [], 'hebergement_net_ht', 0);
            if ($nuitsTotales > 0 && $hebergementNetHt > 0) {
                $caHebergement += $hebergementNetHt * ($nuitsPeriode / $nuitsTotales);
            }
        }

        $caHebergement = (int) round($caHebergement);

        return [
            'periode' => ['du' => $du->toDateString(), 'au' => $au->toDateString(), 'jours' => $jours],
            'nombre_de_logements' => $nombreDeLogements,
            'nuits_disponibles' => $nuitsDisponibles,
            'nuits_occupees' => $nuitsOccupees,
            // Pourcentage 0-100, comme les autres taux du projet (remise_pourcentage, tva…), pas un ratio 0-1.
            'taux_occupation' => $nuitsDisponibles > 0 ? round($nuitsOccupees / $nuitsDisponibles * 100, 2) : 0.0,
            'ca_hebergement' => $caHebergement,
            'revpar' => $nuitsDisponibles > 0 ? (int) round($caHebergement / $nuitsDisponibles) : 0,
            'prix_moyen' => $nuitsOccupees > 0 ? (int) round($caHebergement / $nuitsOccupees) : 0,
        ];
    }
}
