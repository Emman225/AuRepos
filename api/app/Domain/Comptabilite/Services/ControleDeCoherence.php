<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Partenaires\Enums\NatureJuridique;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Support\Collection;

/**
 * Contrôle de cohérence (CdC § 9.3) : les recoupements automatiques cités mot pour mot au
 * cahier des charges. « Il signale, ne corrige jamais » — chaque méthode ne fait QUE renvoyer
 * des anomalies, aucune n'écrit en base.
 *
 * Les huit recoupements, dans l'ordre du CdC :
 *   1. aucune écriture de TVA ou de TDT perdue
 *   2. taux effectif = taux paramétré
 *   3. encaissé ≤ facturé
 *   4. aucun séjour vendu à perte
 *   5. aucun chevauchement de séjours
 *   6. toute caution retenue justifiée
 *   7. tout propriétaire assujetti doté d'une DFE ou d'un RCCM
 *   8. toute retenue à la source cohérente avec le régime déclaré du bénéficiaire
 */
final class ControleDeCoherence
{
    private const TOLERANCE_TAUX = 0.001;

    public function __construct(private readonly SoldeDesSejours $soldes) {}

    /** @return array<string, array<int, array<string, mixed>>> une clé par recoupement */
    public function executer(): array
    {
        return [
            'ecritures_tva_tdt_perdues' => $this->ecrituresTvaTdtPerdues(),
            'taux_effectif_ecarte' => $this->tauxEffectifEcarte(),
            'encaisse_superieur_au_facture' => $this->encaisseSuperieurAuFacture(),
            'sejours_vendus_a_perte' => $this->sejoursVendusAPerte(),
            'chevauchements_de_sejours' => $this->chevauchementsDeSejours(),
            'cautions_retenues_non_justifiees' => $this->cautionsRetenuesNonJustifiees(),
            'proprietaires_assujettis_sans_dfe_ni_rccm' => $this->proprietairesAssujettisSansDfeNiRccm(),
            'regime_fiscal_non_renseigne' => $this->regimeFiscalNonRenseigne(),
        ];
    }

    /** 1. Un séjour dont le devis porte de la TVA ou de la TDT, mais sans facture émise. */
    private function ecrituresTvaTdtPerdues(): array
    {
        $facturesParSejour = Facture::query()->where('type', TypeDeFacture::Facture)->pluck('sejour_id')->flip();

        return Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->get()
            ->filter(function (Sejour $s) use ($facturesParSejour): bool {
                $devis = $s->devis ?? [];
                $porteDeLaTaxe = ((int) ($devis['total_tva'] ?? 0)) > 0 || ((int) ($devis['tdt'] ?? 0)) > 0;

                return $porteDeLaTaxe && ! $facturesParSejour->has($s->id);
            })
            ->map(fn (Sejour $s): array => [
                'sejour' => $s->reference, 'tva' => (int) ($s->devis['total_tva'] ?? 0), 'tdt' => (int) ($s->devis['tdt'] ?? 0),
            ])->values()->all();
    }

    /** 2. Le taux de TVA effectif (TVA ÷ HT) s'écarte du taux figé sur le devis (CdC § 9.3). */
    private function tauxEffectifEcarte(): array
    {
        return Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->get()
            ->map(function (Sejour $s): ?array {
                $devis = $s->devis ?? [];
                $totalHt = (int) ($devis['total_ht'] ?? 0);
                $tauxParametre = $devis['taux']['tva'] ?? null;
                if ($totalHt <= 0 || $tauxParametre === null) {
                    return null;
                }
                $tauxEffectif = round((($devis['total_tva'] ?? 0) / $totalHt) * 100, 4);
                if (abs($tauxEffectif - (float) $tauxParametre) <= self::TOLERANCE_TAUX) {
                    return null;
                }

                return ['sejour' => $s->reference, 'taux_parametre' => (float) $tauxParametre, 'taux_effectif' => $tauxEffectif];
            })->filter()->values()->all();
    }

    /** 3. Le montant encaissé (règlements effectués) ne doit jamais dépasser le net à payer. */
    private function encaisseSuperieurAuFacture(): array
    {
        return Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->get()
            ->map(function (Sejour $s): ?array {
                $solde = $this->soldes->de($s);

                return $solde['encaisse'] > $s->net_a_payer
                    ? ['sejour' => $s->reference, 'net_a_payer' => $s->net_a_payer, 'encaisse' => $solde['encaisse']]
                    : null;
            })->filter()->values()->all();
    }

    /** 4. Le prix de vente hébergement ne doit jamais être inférieur au coût propriétaire. */
    private function sejoursVendusAPerte(): array
    {
        return Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->whereNotNull('prix_proprietaire_par_nuit')
            ->get()
            ->map(function (Sejour $s): ?array {
                $prixVente = (int) ($s->devis['hebergement_net_ht'] ?? 0);
                $cout = (int) $s->prix_proprietaire_par_nuit * $s->nombreDeNuits();

                return $prixVente < $cout
                    ? ['sejour' => $s->reference, 'prix_de_vente' => $prixVente, 'cout_proprietaire' => $cout]
                    : null;
            })->filter()->values()->all();
    }

    /** 5. Deux séjours qui occupent le calendrier du même logement sur des dates qui se chevauchent. */
    private function chevauchementsDeSejours(): array
    {
        $anomalies = collect();
        $parLogement = Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->orderBy('arrivee')->get()->groupBy('logement_id');

        foreach ($parLogement as $sejours) {
            $tries = $sejours->values();
            for ($i = 0; $i < $tries->count() - 1; $i++) {
                for ($j = $i + 1; $j < $tries->count(); $j++) {
                    /** @var Sejour $a */
                    $a = $tries[$i];
                    /** @var Sejour $b */
                    $b = $tries[$j];
                    if ($a->arrivee->lessThan($b->depart) && $b->arrivee->lessThan($a->depart)) {
                        $anomalies->push(['logement_id' => $a->logement_id, 'sejour_a' => $a->reference, 'sejour_b' => $b->reference]);
                    }
                }
            }
        }

        return $anomalies->values()->all();
    }

    /** 6. Une caution retenue sans motif enregistré. */
    private function cautionsRetenuesNonJustifiees(): array
    {
        return Sejour::query()->where('caution_retenue', '>', 0)
            ->where(fn ($q) => $q->whereNull('caution_retenue_motif')->orWhere('caution_retenue_motif', ''))
            ->get()
            ->map(fn (Sejour $s): array => ['sejour' => $s->reference, 'caution_retenue' => $s->caution_retenue])
            ->values()->all();
    }

    /** 7. Un propriétaire assujetti à la TVA sans NCC/RCCM ni pièce DFE validée. */
    private function proprietairesAssujettisSansDfeNiRccm(): array
    {
        return Proprietaire::query()->with('pieces')
            ->where('interne', false)->where('assujetti_tva', true)
            ->get()
            ->filter(function (Proprietaire $p): bool {
                $aUneDfeValidee = $p->pieces->contains(fn ($piece) => $piece->type === TypeDePiece::Dfe && $piece->estValable());

                return blank($p->rccm) && ! $aUneDfeValidee;
            })
            ->map(fn (Proprietaire $p): array => ['proprietaire' => $p->nomAffiche()])
            ->values()->all();
    }

    /** 8. Un propriétaire dont le régime fiscal n'est pas renseigné : la retenue la plus élevée doit s'appliquer (CdC P3-PRO-05). */
    private function regimeFiscalNonRenseigne(): array
    {
        return Proprietaire::query()
            ->where('interne', false)
            ->where('nature', '<>', NatureJuridique::PersonnePhysique->value)
            ->where('regime_fiscal', RegimeFiscal::NonRenseigne->value)
            ->get()
            ->map(fn (Proprietaire $p): array => ['proprietaire' => $p->nomAffiche()])
            ->values()->all();
    }
}
