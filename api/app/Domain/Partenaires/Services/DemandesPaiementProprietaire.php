<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Models\DemandePaiementProprietaire;
use App\Support\Api\ErreurMetier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Demandes de paiement des propriétaires (P3-PRO-04, CdC § 8.7) : plafonnées au solde net
 * (déjà net de retenue), décaissées via App\Domain\Caisse\Services\Caisse::saisirUnDecaissement
 * — le même circuit de preuve à quatre étapes que tout décaissement, jamais un raccourci.
 */
final class DemandesPaiementProprietaire
{
    public function __construct(
        private readonly DetteProprietaire $dette,
        private readonly Caisse $caisse,
        private readonly Parametres $parametres,
    ) {}

    public function demander(Proprietaire $proprietaire, int $montant, User $auteur): DemandePaiementProprietaire
    {
        $this->dette->exigerLeDossierComplet($proprietaire);

        $solde = $this->dette->soldeDu($proprietaire);
        if ($montant > $solde['solde_du']) {
            throw new ErreurMetier(
                sprintf(
                    'Cette demande dépasse le solde net disponible (%s F).',
                    number_format($solde['solde_du'], 0, ',', ' '),
                ),
                'demande_superieure_au_solde',
                422,
            );
        }

        $enAttente = DemandePaiementProprietaire::query()->where('proprietaire_id', $proprietaire->id)->where('etat', 'en_attente')->sum('montant');
        if ($montant + $enAttente > $solde['solde_du']) {
            throw new ErreurMetier('D’autres demandes de ce propriétaire sont déjà en attente : leur total dépasserait le solde net.', 'demandes_en_attente_excedentaires', 422);
        }

        // Retenue de CETTE tranche, figée à la date de la demande (CdC § 8.7) : le montant
        // demandé est déjà net, on reconstitue le brut équivalent pour le bordereau.
        $taux = $solde['retenue_taux'];
        $brutEquivalent = $taux > 0 && $taux < 100 ? (int) round($montant / (1 - $taux / 100)) : $montant;
        $retenueMontant = max(0, $brutEquivalent - $montant);

        return DemandePaiementProprietaire::create([
            'proprietaire_id' => $proprietaire->id, 'montant' => $montant,
            'montant_brut_equivalent' => $brutEquivalent, 'retenue_taux' => $taux,
            'retenue_montant' => $retenueMontant, 'retenue_motif' => $solde['retenue_motif'] ?? null,
            'demande_par' => $auteur->id, 'demande_le' => now(),
        ]);
    }

    public function rejeter(DemandePaiementProprietaire $demande, User $administrateur, string $motif): void
    {
        $this->exigerEnAttente($demande);

        $demande->update(['etat' => 'rejetee', 'decide_par' => $administrateur->id, 'decide_le' => now(), 'motif_rejet' => $motif]);
    }

    /** Décaissement : réutilise Caisse::saisirUnDecaissement (CdC § 8.2), jamais un circuit à part. */
    public function decaisser(DemandePaiementProprietaire $demande, User $administrateur, ModeDeReglement $mode, string $notes, ?string $referenceDuMode = null): Reglement
    {
        $this->exigerEnAttente($demande);

        $proprietaire = $demande->proprietaire()->with('utilisateur')->first();

        return DB::transaction(function () use ($demande, $proprietaire, $administrateur, $mode, $notes, $referenceDuMode): Reglement {
            $reglement = $this->caisse->saisirUnDecaissement(
                $administrateur, $proprietaire->utilisateur, $demande->montant, $mode, $notes, Guichet::DettesPartenaires, $referenceDuMode,
            );

            $demande->update(['etat' => 'decaissee', 'decide_par' => $administrateur->id, 'decide_le' => now(), 'reglement_id' => $reglement->id]);

            return $reglement;
        });
    }

    /** Le bordereau tel qu'émis ; régénéré à l'identique s'il manque sur le disque. */
    public function bordereauPdf(Reglement $reglement): string
    {
        $chemin = 'bordereaux-proprietaire/'.$reglement->id.'.pdf';
        if (Storage::disk('local')->exists($chemin)) {
            return (string) Storage::disk('local')->get($chemin);
        }

        $demande = DemandePaiementProprietaire::query()->where('reglement_id', $reglement->id)->firstOrFail();
        $proprietaire = $demande->proprietaire()->with('utilisateur')->first();
        $p = fn (string $cle) => $this->parametres->valeur($cle);

        $contenu = Pdf::loadView('pdf.bordereau-paiement-proprietaire', [
            'reglement' => $reglement->loadMissing(['tiers', 'agence']), 'demande' => $demande, 'proprietaire' => $proprietaire,
            'entreprise' => ['nom' => $p('entreprise.raison_sociale') ?: $p('general.nom_plateforme')],
            'devise' => $p('general.devise'),
        ])->setPaper('a5')->output();

        Storage::disk('local')->put($chemin, $contenu);

        return $contenu;
    }

    private function exigerEnAttente(DemandePaiementProprietaire $demande): void
    {
        if (! $demande->enAttente()) {
            throw new ErreurMetier('Cette demande a déjà été traitée.', 'demande_deja_traitee');
        }
    }
}
