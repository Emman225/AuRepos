<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Models\ChargeProprietaire;
use App\Domain\Partenaires\Models\RelevePropretaire;
use App\Mail\RelevePropretaireMail;
use App\Support\Api\ErreurMetier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Relevés mensuels des propriétaires (P3-PRO-03, CdC § 7.2) : nuitées vendues (consommées),
 * montant brut, charges refacturées, net à reverser. Génération planifiée (App\Console\Commands\GenererLesRelevesProprietaires),
 * PDF conservé tel qu'émis, envoi par courriel — même circuit que les reçus de caisse.
 */
final class RelevesProprietaires
{
    public function __construct(
        private readonly DetteProprietaire $dette,
        private readonly Parametres $parametres,
    ) {}

    /**
     * Idempotent : un relevé déjà généré pour ce mois n'est jamais régénéré (les charges qu'il a
     * reprises ne doivent jamais se déduire une seconde fois).
     */
    public function genererPourLeMois(Proprietaire $proprietaire, Carbon $mois, ?User $auteur = null): RelevePropretaire
    {
        $periode = $mois->copy()->startOfMonth();

        $existant = RelevePropretaire::query()
            ->where('proprietaire_id', $proprietaire->id)->whereDate('periode', $periode->toDateString())->first();
        if ($existant !== null) {
            return $existant;
        }

        $this->dette->exigerLeDossierComplet($proprietaire);
        $calcul = $this->dette->calculerPourLaPeriode($proprietaire, $periode);

        return DB::transaction(function () use ($proprietaire, $periode, $calcul, $auteur): RelevePropretaire {
            $releve = RelevePropretaire::create([
                'proprietaire_id' => $proprietaire->id, 'periode' => $periode,
                'nuitees_consommees' => $calcul['nuitees_consommees'], 'montant_brut' => $calcul['brut'],
                'charges_refacturees' => $calcul['charges_refacturees'], 'part_cautions' => $calcul['part_cautions'],
                'tva' => $calcul['tva'], 'retenue_taux' => $calcul['retenue_taux'], 'retenue_montant' => $calcul['retenue_montant'],
                'retenue_motif' => $calcul['retenue_motif'], 'montant_net' => $calcul['net'],
                'genere_le' => now(), 'genere_par' => $auteur?->id,
            ]);

            // Chaque charge n'est déduite qu'une seule fois : elle est désormais « reprise ».
            ChargeProprietaire::query()->whereKey($calcul['charges']->pluck('id'))->update(['releve_id' => $releve->id]);

            return $releve;
        });
    }

    /** @return array<int, RelevePropretaire> */
    public function genererPourLeMoisTousProprietaires(Carbon $mois): array
    {
        $releves = [];
        foreach (Proprietaire::query()->cursor() as $proprietaire) {
            try {
                $releves[] = $this->genererPourLeMois($proprietaire, $mois);
            } catch (ErreurMetier $e) {
                // Dossier incomplet (régime fiscal manquant) : on saute ce propriétaire, jamais toute la campagne.
                Log::warning('Relevé propriétaire non généré', ['proprietaire_id' => $proprietaire->id, 'erreur' => $e->getMessage()]);
            }
        }

        return $releves;
    }

    /** Le PDF tel qu'émis ; régénéré à l'identique s'il manque sur le disque. */
    public function pdf(RelevePropretaire $releve): string
    {
        if ($releve->chemin_pdf !== null && Storage::disk('local')->exists($releve->chemin_pdf)) {
            return (string) Storage::disk('local')->get($releve->chemin_pdf);
        }

        $contenu = Pdf::loadView('pdf.releve-proprietaire', $this->donnees($releve))->setPaper('a4')->output();
        $chemin = 'releves-proprietaire/'.$releve->periode->format('Y-m').'/'.$releve->proprietaire_id.'.pdf';
        Storage::disk('local')->put($chemin, $contenu);
        $releve->forceFill(['chemin_pdf' => $chemin])->saveQuietly();

        return $contenu;
    }

    public function attestationPdf(RelevePropretaire $releve): string
    {
        if ($releve->chemin_attestation_pdf !== null && Storage::disk('local')->exists($releve->chemin_attestation_pdf)) {
            return (string) Storage::disk('local')->get($releve->chemin_attestation_pdf);
        }

        $contenu = Pdf::loadView('pdf.attestation-retenue-proprietaire', $this->donnees($releve))->setPaper('a4')->output();
        $chemin = 'attestations-retenue/'.$releve->periode->format('Y-m').'/'.$releve->proprietaire_id.'.pdf';
        Storage::disk('local')->put($chemin, $contenu);
        $releve->forceFill(['chemin_attestation_pdf' => $chemin])->saveQuietly();

        return $contenu;
    }

    /** Envoi UNIQUE (CdC § 8.4, même règle que les reçus) ; renvoi manuel possible via `envoyer`. */
    public function envoyerUneFois(RelevePropretaire $releve): bool
    {
        if ($releve->envoye_le !== null) {
            return false;
        }

        $parti = $this->envoyer($releve);
        if ($parti) {
            $releve->forceFill(['envoye_le' => now()])->saveQuietly();
        }

        return $parti;
    }

    public function envoyer(RelevePropretaire $releve): bool
    {
        try {
            $this->pdf($releve);
            $this->attestationPdf($releve);
            $proprietaire = $releve->proprietaire()->with('utilisateur')->first();
            Mail::to($proprietaire->utilisateur->email)->send(new RelevePropretaireMail($releve));

            return true;
        } catch (Throwable $e) {
            Log::error('Envoi du relevé propriétaire impossible', ['releve' => $releve->id, 'erreur' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function donnees(RelevePropretaire $releve): array
    {
        $proprietaire = $releve->proprietaire()->with('utilisateur')->first();
        $releve->loadMissing('charges');
        $p = fn (string $cle) => $this->parametres->valeur($cle);

        return [
            'releve' => $releve,
            'proprietaire' => $proprietaire,
            'charges' => $releve->charges,
            'modeRemuneration' => $proprietaire->mode_remuneration === ModeDeRemuneration::Commission
                ? 'commission sur prix de vente' : 'prix négocié',
            'entreprise' => [
                'nom' => $p('entreprise.raison_sociale') ?: $p('general.nom_plateforme'), 'siege' => $p('entreprise.siege'),
                'telephone' => $p('entreprise.telephone') ?: $p('general.telephone'), 'courriel' => $p('entreprise.courriel') ?: $p('general.courriel'),
                'ncc' => $p('entreprise.ncc'), 'rccm' => $p('entreprise.rccm'), 'regime' => $p('entreprise.regime_imposition'),
            ],
            'devise' => $p('general.devise'),
        ];
    }
}
