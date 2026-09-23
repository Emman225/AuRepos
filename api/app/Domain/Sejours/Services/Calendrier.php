<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'écriture du calendrier. « Le serveur vérifie la disponibilité au moment de
 * la réservation, jamais sur la foi du téléphone ou d'un canal externe » (CdC § 4).
 *
 * On ne vérifie pas PUIS on écrit (deux demandes simultanées passeraient toutes les deux
 * la vérification) : on écrit, et c'est la contrainte d'exclusion PostgreSQL qui tranche.
 */
final class Calendrier
{
    /** Code PostgreSQL d'une violation de contrainte d'exclusion. */
    private const VIOLATION_D_EXCLUSION = '23P01';

    /** Lecture seule, pour la recherche et les écrans : ne garantit rien, c'est `occuper()` qui garantit. */
    public function estLibre(Logement|int $logement, Carbon $arrivee, Carbon $depart, ?int $saufSejourId = null): bool
    {
        return ! DB::table('occupations')
            ->where('logement_id', $logement instanceof Logement ? $logement->id : $logement)
            ->whereRaw('periode && daterange(?, ?, \'[)\')', [$arrivee->toDateString(), $depart->toDateString()])
            ->when($saufSejourId, fn ($q) => $q->where(fn ($s) => $s->whereNull('sejour_id')->orWhere('sejour_id', '<>', $saufSejourId)))
            ->exists();
    }

    /**
     * Pose (ou déplace) l'occupation d'un séjour. À appeler dans la transaction qui crée ou modifie le séjour.
     *
     * @throws ErreurMetier `dates_indisponibles` si une autre occupation tient déjà une de ces nuits
     */
    public function occuper(Sejour $sejour): void
    {
        if (! $sejour->etat->occupeLeCalendrier()) {
            $this->liberer($sejour);

            return;
        }

        // Un séjour occupe [arrivée, départ[ : le jour du départ reste libre pour l'arrivée suivante.
        $this->ecrire(
            ['sejour_id' => $sejour->id],
            $sejour->logement_id,
            $sejour->arrivee->toDateString(),
            $sejour->depart->toDateString(),
        );
    }

    /** Annulation, no-show, expiration d'une demande : les dates reviennent à la vente. */
    public function liberer(Sejour $sejour): void
    {
        DB::table('occupations')->where('sejour_id', $sejour->id)->delete();
    }

    /**
     * @throws ErreurMetier `dates_indisponibles` si un séjour ou un autre blocage tient déjà ces dates
     */
    public function bloquer(Logement $logement, Carbon $debut, Carbon $fin, string $motif, ?string $commentaire, ?User $auteur): BlocageCalendrier
    {
        return DB::transaction(function () use ($logement, $debut, $fin, $motif, $commentaire, $auteur): BlocageCalendrier {
            $blocage = BlocageCalendrier::create([
                'logement_id' => $logement->id, 'debut' => $debut->toDateString(), 'fin' => $fin->toDateString(),
                'motif' => $motif, 'commentaire' => $commentaire, 'cree_par' => $auteur?->id,
            ]);

            // « Du … au … » : les deux jours sont bloqués, d'où le lendemain de la fin comme borne ouverte.
            $this->ecrire(['blocage_id' => $blocage->id], $logement->id, $debut->toDateString(), $fin->copy()->addDay()->toDateString());

            return $blocage;
        });
    }

    public function debloquer(BlocageCalendrier $blocage): void
    {
        $blocage->delete(); // l'occupation part avec lui (suppression en cascade)
    }

    /**
     * Nuits occupées d'un logement sur une période (fiche publique, CdC § 5.1) : chaque nuit
     * couverte par une occupation, séjour ou blocage confondus — le public ne distingue pas pourquoi.
     *
     * @return list<string> dates au format Y-m-d
     */
    public function joursOccupes(Logement $logement, Carbon $debut, Carbon $finExclue): array
    {
        $periodes = DB::table('occupations')
            ->where('logement_id', $logement->id)
            ->whereRaw('periode && daterange(?, ?, \'[)\')', [$debut->toDateString(), $finExclue->toDateString()])
            ->selectRaw('lower(periode) as debut, upper(periode) as fin')
            ->get();

        $jours = [];
        foreach ($periodes as $periode) {
            for ($jour = Carbon::parse($periode->debut)->max($debut); $jour->lt(Carbon::parse($periode->fin)->min($finExclue)); $jour->addDay()) {
                $jours[] = $jour->toDateString();
            }
        }

        sort($jours);

        return array_values(array_unique($jours));
    }

    /** @param array{sejour_id: int}|array{blocage_id: int} $source */
    private function ecrire(array $source, int $logementId, string $debut, string $finExclue): void
    {
        try {
            // Transaction imbriquée = point de sauvegarde : le refus de la base n'annule pas la transaction de l'appelant.
            DB::transaction(function () use ($source, $logementId, $debut, $finExclue): void {
                DB::table('occupations')->where($source)->delete();
                DB::insert(
                    'INSERT INTO occupations (logement_id, sejour_id, blocage_id, periode, created_at, updated_at) VALUES (?, ?, ?, daterange(?, ?, \'[)\'), now(), now())',
                    [$logementId, $source['sejour_id'] ?? null, $source['blocage_id'] ?? null, $debut, $finExclue],
                );
            });
        } catch (QueryException $e) {
            if ($e->getCode() === self::VIOLATION_D_EXCLUSION) {
                throw new ErreurMetier(
                    'Ces dates ne sont plus disponibles pour ce logement.',
                    'dates_indisponibles',
                );
            }
            throw $e;
        }
    }
}
