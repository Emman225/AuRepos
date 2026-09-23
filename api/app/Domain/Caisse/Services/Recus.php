<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Imputation;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\RecuDeReglementMail;
use App\Support\Api\ErreurMetier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;
use Throwable;

/**
 * Reçus de la caisse (CdC § 8.4). « Tant que le circuit n'est pas terminé, le reçu n'existe pas » :
 * le numéro est attribué à la finalisation, dans la même transaction ; le PDF est conservé tel qu'émis ;
 * le courriel part UNE fois, et un envoi manqué ne bloque jamais l'opération.
 */
final class Recus
{
    public function __construct(
        private readonly Numerotation $numerotation,
        private readonly Parametres $parametres,
    ) {}

    /** À appeler DANS la transaction de finalisation : le numéro et l'état « effectué » naissent ensemble. */
    public function attribuerLeNumero(Reglement $reglement): ?string
    {
        $prefixe = $reglement->estUnEncaissement() ? $reglement->guichet->prefixeDuRecu() : null;
        if ($prefixe === null || $reglement->numero_recu !== null) {
            return $reglement->numero_recu;
        }

        $annee = (int) now()->year;
        $numero = sprintf('%s-%d-%03d', $prefixe, $annee, $this->numerotation->suivant('recus', $annee));
        $reglement->forceFill(['numero_recu' => $numero])->save();

        return $numero;
    }

    /** Le PDF tel qu'émis ; régénéré à l'identique s'il manque sur le disque. */
    public function pdf(Reglement $reglement): string
    {
        if ($reglement->numero_recu === null || $reglement->etat !== EtatDuReglement::Effectue) {
            throw new ErreurMetier('Ce règlement n’a pas de reçu : le reçu n’existe qu’une fois le règlement effectué.', 'recu_inexistant', 404);
        }

        if ($reglement->recu_chemin !== null && Storage::disk('local')->exists($reglement->recu_chemin)) {
            return (string) Storage::disk('local')->get($reglement->recu_chemin);
        }

        $contenu = Pdf::loadView('pdf.recu', $this->donnees($reglement))->setPaper('a5')->output();
        $chemin = 'recus/'.$reglement->finalise_le?->format('Y').'/'.$reglement->numero_recu.'.pdf';
        Storage::disk('local')->put($chemin, $contenu);
        $reglement->forceFill(['recu_chemin' => $chemin])->saveQuietly();

        return $contenu;
    }

    /** Envoi UNIQUE à la finalisation (CdC § 8.4). Rend vrai si le courriel est parti maintenant. */
    public function envoyerUneFois(Reglement $reglement): bool
    {
        if ($reglement->recu_envoye_le !== null || $reglement->numero_recu === null) {
            return false;
        }

        $parti = $this->envoyer($reglement);
        if ($parti) {
            $reglement->forceFill(['recu_envoye_le' => now()])->saveQuietly();
        }

        return $parti;
    }

    /** Renvoi manuel, autant de fois qu'il faut. */
    public function envoyer(Reglement $reglement): bool
    {
        try {
            $this->pdf($reglement);     // produit et conserve le PDF avant l’envoi : le courriel le relira depuis le disque
            Mail::to($reglement->tiers->email)->send(new RecuDeReglementMail($reglement));

            return true;
        } catch (Throwable $e) {
            // « Un envoi manqué ne bloque jamais l'opération » : on journalise, le renvoi manuel reste possible.
            Log::error('Envoi du reçu impossible', ['reglement' => $reglement->reference, 'erreur' => $e->getMessage()]);

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function donnees(Reglement $reglement): array
    {
        $reglement->loadMissing(['tiers', 'agence', 'auteur', 'imputations.affaire']);
        $p = fn (string $cle) => $this->parametres->valeur($cle);
        $impute = (int) $reglement->imputations->sum('montant');

        return [
            'reglement' => $reglement,
            'entreprise' => [
                'nom' => $p('entreprise.raison_sociale') ?: $p('general.nom_plateforme'), 'siege' => $p('entreprise.siege'),
                'telephone' => $p('entreprise.telephone') ?: $p('general.telephone'), 'courriel' => $p('entreprise.courriel') ?: $p('general.courriel'),
                'ncc' => $p('entreprise.ncc'), 'rccm' => $p('entreprise.rccm'), 'regime' => $p('entreprise.regime_imposition'),
            ],
            'devise' => $p('general.devise'),
            'enLettres' => self::enLettres($reglement->montant),
            'lignes' => $reglement->imputations->map(fn (Imputation $i): array => [
                'libelle' => $i->affaire instanceof Sejour
                    ? 'Séjour '.$i->affaire->reference.' — du '.$i->affaire->arrivee->format('d/m/Y').' au '.$i->affaire->depart->format('d/m/Y')
                    : $i->affaire_type.' n° '.$i->affaire_id,
                'montant' => $i->montant,
            ])->all(),
            // Part gardée en avance : dépôt d'avance, ou surplus d'un encaissement.
            'enAvance' => max(0, $reglement->montant - $impute),
        ];
    }

    /**
     * 66 361 → « soixante-six mille trois cent soixante-et-un ».
     *
     * « vingt » et « cent » prennent un s quand ils terminent le nombre : quatre-vingtS mille,
     * mais quatre-vingt-dix. La bibliothèque ICU ne fait pas cet accord.
     */
    public static function enLettres(int $montant): string
    {
        $lettres = (string) (new NumberFormatter('fr', NumberFormatter::SPELLOUT))->format($montant);

        return (string) preg_replace(['/\bvingt$/', '/\bcent$/'], ['vingts', 'cents'], $lettres);
    }
}
