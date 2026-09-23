<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Support\Carbon;

/**
 * Export des écritures comptables vers Sage ou Excel (CdC § 9.3, P3-CPT-06). Module déjà
 * cadré pour Mon Gravier ; faute du fichier de cadrage dans ce dépôt (voir
 * `config/comptabilite.php`), l'export ici reprend le strict nécessaire déjà dérivable du
 * code : les factures transmises (produit, TVA, TDT, taxe de séjour, chacun dans son compte
 * de famille) et les règlements effectués (encaissements et décaissements). Rien n'est
 * recalculé : chaque montant vient du devis figé du séjour (`Sejour::devis`) ou du montant
 * déjà enregistré sur le règlement.
 *
 * @phpstan-type Ecriture array{date: string, journal: string, compte: string, libelle: string, debit: int, credit: int, piece: string}
 */
final class ExportComptable
{
    /** @return list<array<string, mixed>> */
    public function genererEcritures(Carbon $du, Carbon $au): array
    {
        $comptes = (array) config('comptabilite.comptes');
        $ecritures = collect();

        $factures = Facture::query()->with('sejour')
            ->where('type', TypeDeFacture::Facture)
            ->whereNotNull('transmise_le')
            ->whereBetween('transmise_le', [$du, $au])
            ->get();

        foreach ($factures as $facture) {
            $ecritures = $ecritures->merge($this->ecrituresDeLaFacture($facture, $comptes));
        }

        $reglements = Reglement::query()
            ->where('etat', EtatDuReglement::Effectue)
            ->whereBetween('saisi_le', [$du, $au])
            ->get();

        foreach ($reglements as $reglement) {
            $ecritures = $ecritures->merge($this->ecrituresDuReglement($reglement, $comptes));
        }

        return $ecritures->values()->all();
    }

    /** @param  array<string, string>  $comptes @return list<array<string, mixed>> */
    private function ecrituresDeLaFacture(Facture $facture, array $comptes): array
    {
        /** @var Sejour|null $sejour */
        $sejour = $facture->sejour;
        $devis = $sejour?->devis ?? [];
        $date = $facture->transmise_le->format('Y-m-d');
        $piece = $facture->numero;

        $lignes = collect([
            ['famille' => 'hebergement', 'montant' => (int) ($devis['hebergement_net_ht'] ?? 0)],
            ['famille' => 'extras', 'montant' => (int) ($devis['extras_ht'] ?? 0)],
            ['famille' => 'transferts', 'montant' => (int) ($devis['transfert_ht'] ?? 0)],
            ['famille' => 'tva_collectee', 'montant' => (int) ($devis['total_tva'] ?? 0)],
            ['famille' => 'tdt_collectee', 'montant' => (int) ($devis['tdt'] ?? 0)],
            ['famille' => 'taxe_sejour_collectee', 'montant' => (int) ($devis['taxe_de_sejour'] ?? 0)],
        ])->filter(fn (array $l) => $l['montant'] > 0);

        $credits = $lignes->map(fn (array $l): array => [
            'date' => $date, 'journal' => 'VE', 'compte' => $comptes[$l['famille']] ?? $l['famille'],
            'libelle' => 'Facture '.$piece, 'debit' => 0, 'credit' => $l['montant'], 'piece' => $piece,
        ])->values();

        $debit = [[
            'date' => $date, 'journal' => 'VE', 'compte' => $comptes['clients'], 'libelle' => 'Facture '.$piece,
            'debit' => $facture->montant_ttc, 'credit' => 0, 'piece' => $piece,
        ]];

        return [...$debit, ...$credits->all()];
    }

    /** @param  array<string, string>  $comptes @return list<array<string, mixed>> */
    private function ecrituresDuReglement(Reglement $reglement, array $comptes): array
    {
        $date = $reglement->saisi_le->format('Y-m-d');
        $piece = $reglement->reference;

        if ($reglement->sens === 'encaissement') {
            return [
                ['date' => $date, 'journal' => 'CA', 'compte' => $comptes['caisse'], 'libelle' => 'Encaissement '.$piece, 'debit' => $reglement->montant, 'credit' => 0, 'piece' => $piece],
                ['date' => $date, 'journal' => 'CA', 'compte' => $comptes['clients'], 'libelle' => 'Encaissement '.$piece, 'debit' => 0, 'credit' => $reglement->montant, 'piece' => $piece],
            ];
        }

        $compteContrepartie = match ($reglement->guichet) {
            Guichet::DettesPartenaires => $comptes['fournisseurs_partenaires'],
            Guichet::Cautions => $comptes['cautions'],
            default => $comptes['clients'],
        };

        return [
            ['date' => $date, 'journal' => 'CA', 'compte' => $compteContrepartie, 'libelle' => 'Décaissement '.$piece, 'debit' => $reglement->montant, 'credit' => 0, 'piece' => $piece],
            ['date' => $date, 'journal' => 'CA', 'compte' => $comptes['caisse'], 'libelle' => 'Décaissement '.$piece, 'debit' => 0, 'credit' => $reglement->montant, 'piece' => $piece],
        ];
    }
}
