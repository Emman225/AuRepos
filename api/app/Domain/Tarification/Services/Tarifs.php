<?php

namespace App\Domain\Tarification\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Tarification\Models\LigneDeGrille;
use App\Domain\Tarification\Models\Saison;
use App\Domain\Tarification\Models\TrancheDuree;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lecture du tarif par nuit d'un logement (CdC § 7.3).
 *
 * Ordre, du plus précis au plus général :
 *   1. ligne de grille propre au logement ;   2. ligne de grille de son type ;
 *   3. prix de vente du logement.
 *
 * La saison se juge NUIT PAR NUIT (un séjour à cheval sur deux saisons paie chaque nuit
 * à son tarif) ; la tranche de durée se juge sur la durée TOTALE du séjour.
 * Un événement l'emporte sur la saison basse ou haute qu'il recouvre.
 */
final class Tarifs
{
    /** @var Collection<int, Saison>|null */
    private ?Collection $saisons = null;

    /** @var Collection<int, TrancheDuree>|null */
    private ?Collection $tranches = null;

    public function saisonDu(Carbon $jour): ?Saison
    {
        $couvrantes = $this->saisons()->filter(fn (Saison $s) => $s->couvre($jour));

        return $couvrantes->first(fn (Saison $s) => $s->estUnEvenement()) ?? $couvrantes->first();
    }

    public function tranchePour(int $nuits): ?TrancheDuree
    {
        return $this->tranches()->first(fn (TrancheDuree $t) => $t->couvre($nuits));
    }

    /**
     * Détail nuit par nuit d'un séjour : date, saison, tarif et d'où il vient.
     * $depart est le jour du départ : il n'est pas une nuit facturée.
     *
     * @return list<array{date: string, saison: string|null, tarif: int, origine: string}>
     *
     * @throws ErreurMetier si une nuit n'a aucun tarif
     */
    public function nuitees(Logement $logement, Carbon $arrivee, Carbon $depart): array
    {
        $nombre = (int) $arrivee->copy()->startOfDay()->diffInDays($depart->copy()->startOfDay());
        if ($nombre < 1) {
            throw new ErreurMetier('Le départ doit suivre l’arrivée d’au moins une nuit.', 'periode_invalide', 422);
        }

        $tranche = $this->tranchePour($nombre);
        $lignes = LigneDeGrille::query()
            ->where(fn ($q) => $q->where('logement_id', $logement->id)->orWhere('type_logement_id', $logement->type_logement_id))
            ->when($tranche, fn ($q) => $q->where('tranche_duree_id', $tranche->id), fn ($q) => $q->whereRaw('false'))
            ->get();

        $nuitees = [];
        for ($jour = $arrivee->copy()->startOfDay(); $jour->lt($depart->copy()->startOfDay()); $jour->addDay()) {
            $saison = $this->saisonDu($jour);
            $propre = $saison ? $lignes->first(fn (LigneDeGrille $l) => $l->logement_id === $logement->id && $l->saison_id === $saison->id) : null;
            $duType = $saison ? $lignes->first(fn (LigneDeGrille $l) => $l->type_logement_id === $logement->type_logement_id && $l->saison_id === $saison->id) : null;

            [$tarif, $origine] = match (true) {
                $propre !== null => [$propre->tarif, 'grille du logement'],
                $duType !== null => [$duType->tarif, 'grille du type'],
                $logement->prix_vente !== null => [$logement->prix_vente, 'prix de vente du logement'],
                default => throw new ErreurMetier(
                    'Aucun tarif n’est défini pour la nuit du '.$jour->format('d/m/Y').' : ni grille, ni prix de vente.',
                    'nuit_sans_tarif',
                    422,
                ),
            };

            $nuitees[] = ['date' => $jour->format('Y-m-d'), 'saison' => $saison?->nom, 'tarif' => $tarif, 'origine' => $origine];
        }

        return $nuitees;
    }

    /**
     * Tarif indicatif « à partir de » pour une saison donnée, sur la tranche de durée la plus
     * courte (fiche logement publique, CdC § 5.1). Simple aperçu, jamais un calcul de séjour :
     * `nuitees()` reste la seule source du prix engageant un client.
     *
     * @return list<array{saison: string, categorie: string, debut: string, fin: string, tarif: int}>
     */
    public function parSaisonPour(Logement $logement): array
    {
        $tranche = $this->tranches()->first();
        if ($tranche === null) {
            return [];
        }

        $lignes = LigneDeGrille::query()
            ->where('tranche_duree_id', $tranche->id)
            ->where(fn ($q) => $q->where('logement_id', $logement->id)->orWhere('type_logement_id', $logement->type_logement_id))
            ->get();

        $resultat = [];
        foreach ($this->saisons()->reject(fn (Saison $s) => $s->estUnEvenement()) as $saison) {
            $propre = $lignes->first(fn (LigneDeGrille $l) => $l->logement_id === $logement->id && $l->saison_id === $saison->id);
            $duType = $lignes->first(fn (LigneDeGrille $l) => $l->type_logement_id === $logement->type_logement_id && $l->saison_id === $saison->id);
            $tarif = $propre->tarif ?? $duType->tarif ?? $logement->prix_vente;

            if ($tarif !== null) {
                $resultat[] = [
                    'saison' => $saison->nom, 'categorie' => $saison->categorie,
                    'debut' => $saison->date_debut->format('Y-m-d'), 'fin' => $saison->date_fin->format('Y-m-d'),
                    'tarif' => $tarif,
                ];
            }
        }

        return $resultat;
    }

    /** @return Collection<int, Saison> */
    public function saisons(): Collection
    {
        return $this->saisons ??= Saison::query()->where('actif', true)->orderBy('date_debut')->get();
    }

    /** @return Collection<int, TrancheDuree> */
    public function tranches(): Collection
    {
        return $this->tranches ??= TrancheDuree::query()->where('actif', true)->orderBy('nuits_min')->get();
    }
}
