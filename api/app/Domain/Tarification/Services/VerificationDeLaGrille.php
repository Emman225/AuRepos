<?php

namespace App\Domain\Tarification\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Tarification\Models\LigneDeGrille;
use App\Domain\Tarification\Models\Saison;
use App\Domain\Tarification\Models\TrancheDuree;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Vérification automatique de la grille (CdC § 7.3) : « la vérification doit être
 * MUETTE avant de vendre ». Elle signale, ne corrige jamais.
 *
 *   bloquant    — un client pourrait tomber sur une nuit sans tarif, mal tarifée ou vendue à perte ;
 *   information — la grille a un trou, mais le prix de vente du logement prend le relais.
 */
final class VerificationDeLaGrille
{
    /** On vérifie la couverture des saisons sur l'année qui vient : c'est l'horizon des réservations. */
    public const HORIZON_EN_JOURS = 365;

    /** @return list<array{niveau: string, code: string, message: string}> */
    public function anomalies(): array
    {
        $saisons = Saison::query()->where('actif', true)->orderBy('date_debut')->get();
        $tranches = TrancheDuree::query()->where('actif', true)->orderBy('nuits_min')->get();

        return [
            ...$this->tranches($tranches),
            ...$this->saisons($saisons),
            ...$this->couverture($saisons, $tranches),
            ...$this->ventesAPerte(),
        ];
    }

    public function estMuette(): bool
    {
        return array_filter($this->anomalies(), fn (array $a) => $a['niveau'] === 'bloquant') === [];
    }

    /**
     * @param  Collection<int, TrancheDuree>  $tranches
     * @return list<array{niveau: string, code: string, message: string}>
     */
    private function tranches(Collection $tranches): array
    {
        if ($tranches->isEmpty()) {
            return [$this->info('aucune_tranche', 'Aucune tranche de durée n’est définie : tous les séjours se vendront au prix de vente du logement.')];
        }

        $anomalies = [];
        if ($tranches->first()->nuits_min > 1) {
            $anomalies[] = $this->bloquant('trou_de_duree', 'Les tranches de durée ne commencent pas à 1 nuit : les séjours de 1 à '.($tranches->first()->nuits_min - 1).' nuit(s) n’ont pas de tranche.');
        }

        foreach ($tranches->values() as $i => $tranche) {
            $suivante = $tranches->values()->get($i + 1);
            if ($suivante === null) {
                if ($tranche->nuits_max !== null) {
                    $anomalies[] = $this->bloquant('trou_de_duree', "Aucune tranche ne couvre les séjours de plus de {$tranche->nuits_max} nuits : la dernière tranche devrait être « et plus ».");
                }
                break;
            }
            if ($tranche->nuits_max === null || $tranche->nuits_max >= $suivante->nuits_min) {
                $anomalies[] = $this->bloquant('chevauchement_de_durees', "Les tranches « {$tranche->nom} » et « {$suivante->nom} » se chevauchent.");
            } elseif ($tranche->nuits_max + 1 < $suivante->nuits_min) {
                $anomalies[] = $this->bloquant('trou_de_duree', 'Aucune tranche ne couvre les séjours de '.($tranche->nuits_max + 1).' à '.($suivante->nuits_min - 1).' nuit(s).');
            }
        }

        return $anomalies;
    }

    /**
     * @param  Collection<int, Saison>  $saisons
     * @return list<array{niveau: string, code: string, message: string}>
     */
    private function saisons(Collection $saisons): array
    {
        $anomalies = [];
        $courantes = $saisons->reject(fn (Saison $s) => $s->estUnEvenement())->values();
        $evenements = $saisons->filter(fn (Saison $s) => $s->estUnEvenement())->values();

        // Un événement PEUT recouvrir une saison ; deux saisons, ou deux événements, ne se chevauchent pas.
        foreach ([$courantes, $evenements] as $groupe) {
            foreach ($groupe as $i => $a) {
                foreach ($groupe->slice($i + 1) as $b) {
                    if ($a->date_debut->lte($b->date_fin) && $b->date_debut->lte($a->date_fin)) {
                        $anomalies[] = $this->bloquant('chevauchement_de_saisons', "Les saisons « {$a->nom} » et « {$b->nom} » se chevauchent.");
                    }
                }
            }
        }

        if ($courantes->isEmpty()) {
            $anomalies[] = $this->info('aucune_saison', 'Aucune saison n’est définie : tous les séjours se vendront au prix de vente du logement.');

            return $anomalies;
        }

        // Trous de dates entre aujourd'hui et la fin de l'horizon des réservations.
        $debut = Carbon::today();
        $fin = Carbon::today()->addDays(self::HORIZON_EN_JOURS);
        $curseur = $debut->copy();
        foreach ($courantes as $saison) {
            if ($saison->date_fin->lt($curseur)) {
                continue;
            }
            if ($saison->date_debut->gt($curseur)) {
                $anomalies[] = $this->info('trou_de_dates', 'Aucune saison du '.$curseur->format('d/m/Y').' au '.$saison->date_debut->copy()->subDay()->min($fin)->format('d/m/Y').' : le prix de vente du logement s’appliquera.');
            }
            $curseur = $saison->date_fin->copy()->addDay();
            if ($curseur->gt($fin)) {
                break;
            }
        }
        if ($curseur->lte($fin)) {
            $anomalies[] = $this->info('trou_de_dates', 'Aucune saison après le '.$curseur->copy()->subDay()->format('d/m/Y').' : le prix de vente du logement s’appliquera.');
        }

        return $anomalies;
    }

    /**
     * Pour chaque type qui a des logements : chaque croisement saison × tranche a-t-il un tarif ?
     *
     * @param  Collection<int, Saison>  $saisons
     * @param  Collection<int, TrancheDuree>  $tranches
     * @return list<array{niveau: string, code: string, message: string}>
     */
    private function couverture(Collection $saisons, Collection $tranches): array
    {
        $anomalies = [];
        $aVenir = $saisons->filter(fn (Saison $s) => $s->date_fin->gte(Carbon::today()));
        $lignes = LigneDeGrille::all();

        foreach (TypeLogement::query()->where('actif', true)->orderBy('ordre')->get() as $type) {
            $logements = Logement::query()->where('type_logement_id', $type->id)->get();
            if ($logements->isEmpty()) {
                continue;
            }

            // Sans grille du tout, tout repose sur le prix de vente de chaque logement.
            $sansRepli = $logements->filter(fn (Logement $l) => $l->prix_vente === null);

            foreach ($aVenir as $saison) {
                foreach ($tranches as $tranche) {
                    $duType = $lignes->contains(fn (LigneDeGrille $l) => $l->type_logement_id === $type->id && $l->saison_id === $saison->id && $l->tranche_duree_id === $tranche->id);
                    if ($duType) {
                        continue;
                    }
                    $nonCouverts = $sansRepli->reject(fn (Logement $l) => $lignes->contains(
                        fn (LigneDeGrille $g) => $g->logement_id === $l->id && $g->saison_id === $saison->id && $g->tranche_duree_id === $tranche->id,
                    ));

                    $anomalies[] = $nonCouverts->isNotEmpty()
                        ? $this->bloquant('type_non_tarife', "Type « {$type->nom} » non tarifé en « {$saison->nom} » pour « {$tranche->nom} », et {$nonCouverts->count()} logement(s) sans prix de vente : ".$nonCouverts->pluck('reference')->implode(', ').'.')
                        : $this->info('repli_sur_prix_de_vente', "Type « {$type->nom} » non tarifé en « {$saison->nom} » pour « {$tranche->nom} » : le prix de vente de chaque logement s’appliquera.");
                }
            }

            if (($aVenir->isEmpty() || $tranches->isEmpty()) && $sansRepli->isNotEmpty()) {
                $anomalies[] = $this->bloquant('type_non_tarife', "Type « {$type->nom} » : {$sansRepli->count()} logement(s) sans grille ni prix de vente : ".$sansRepli->pluck('reference')->implode(', ').'.');
            }
        }

        return $anomalies;
    }

    /**
     * Aucun séjour ne se vend à perte : un tarif de grille sous le prix propriétaire d'un logement concerné.
     *
     * @return list<array{niveau: string, code: string, message: string}>
     */
    private function ventesAPerte(): array
    {
        $anomalies = [];
        $logements = Logement::query()->whereNotNull('prix_proprietaire')->get();

        foreach (LigneDeGrille::query()->with(['saison', 'tranche'])->get() as $ligne) {
            $concernes = $logements->filter(fn (Logement $l) => $ligne->logement_id !== null
                ? $l->id === $ligne->logement_id
                : $l->type_logement_id === $ligne->type_logement_id);

            foreach ($concernes as $logement) {
                if ($ligne->tarif < $logement->prix_proprietaire) {
                    $anomalies[] = $this->bloquant('vente_a_perte', sprintf(
                        'Vente à perte : %s serait vendu %s F la nuit en « %s » (%s), sous son prix propriétaire de %s F.',
                        $logement->reference, number_format($ligne->tarif, 0, ',', ' '), $ligne->saison->nom, $ligne->tranche->nom,
                        number_format((int) $logement->prix_proprietaire, 0, ',', ' '),
                    ));
                }
            }
        }

        return $anomalies;
    }

    /** @return array{niveau: string, code: string, message: string} */
    private function bloquant(string $code, string $message): array
    {
        return ['niveau' => 'bloquant', 'code' => $code, 'message' => $message];
    }

    /** @return array{niveau: string, code: string, message: string} */
    private function info(string $code, string $message): array
    {
        return ['niveau' => 'information', 'code' => $code, 'message' => $message];
    }
}
