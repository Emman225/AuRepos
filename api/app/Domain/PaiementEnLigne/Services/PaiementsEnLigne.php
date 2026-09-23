<?php

namespace App\Domain\PaiementEnLigne\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\Recus;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\PaiementEnLigne\Contracts\PasserelleDePaiement;
use App\Domain\PaiementEnLigne\Models\PaiementEnLigne;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paiement en ligne (CdC § 5.2 et § 8.3) — porté de Mon Gravier, dont l'audit relevait
 * la solidité du dispositif : rappel idempotent, re-vérification serveur à serveur,
 * confirmation manuelle possible et reprise planifiée.
 *
 * Deux règles tiennent tout :
 *   1. On ne croit JAMAIS ce que raconte le navigateur du client, ni même le contenu d'un rappel :
 *      avant de conclure, on redemande à la passerelle, de serveur à serveur.
 *   2. Un paiement ne produit son règlement qu'UNE fois, quel que soit le nombre de rappels
 *      ou de vérifications. Un verrou en base le garantit.
 */
final class PaiementsEnLigne
{
    public function __construct(
        private readonly PasserelleDePaiement $passerelle,
        private readonly Parametres $parametres,
        private readonly SoldeDesSejours $soldes,
        private readonly Recus $recus,
        private readonly JournalAudit $journal,
    ) {}

    /** Ouvre une transaction et rend l'adresse où envoyer le client. */
    public function initier(Sejour $sejour, ?int $montantDemande = null): PaiementEnLigne
    {
        if (! $this->parametres->valeur('comptant.paiement_en_ligne_actif')) {
            throw new ErreurMetier('Le paiement en ligne n’est pas disponible pour le moment : réglez en agence.', 'paiement_en_ligne_indisponible', 422);
        }
        if (! $this->passerelle->estConfiguree()) {
            throw new ErreurMetier('Le paiement en ligne n’est pas encore configuré sur cette plateforme.', 'passerelle_non_configuree', 503);
        }
        if (in_array($sejour->etat, [EtatDuSejour::Annule, EtatDuSejour::NoShow], true)) {
            throw new ErreurMetier('Ce séjour est clos : il n’y a plus rien à régler.', 'affaire_morte', 422);
        }

        // Le montant vient du SERVEUR : reste dû, ou part demandée, jamais un prix envoyé par le client.
        $resteDu = $this->soldes->de($sejour)['reste_du'];
        $montant = min($montantDemande ?? $resteDu, $resteDu);
        if ($montant <= 0) {
            throw new ErreurMetier('Ce séjour est déjà soldé.', 'rien_a_payer', 422);
        }

        $plafond = (int) $this->parametres->valeur('general.plafond_paiement_en_ligne');
        if ($plafond > 0 && $montant > $plafond) {
            throw new ErreurMetier(
                'Ce montant dépasse le plafond du paiement en ligne ('.number_format($plafond, 0, ',', ' ').' F) : réglez en agence ou par virement.',
                'plafond_paiement_en_ligne', 422,
            );
        }

        $paiement = PaiementEnLigne::create([
            'reference' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'sejour_id' => $sejour->id, 'client_id' => $sejour->client_id, 'montant' => $montant,
            'passerelle' => $this->passerelle->nom(),
        ]);

        $ouverture = $this->passerelle->initier($paiement, $this->urlDeRetour($paiement), $this->urlDeRappel());

        $paiement->update([
            'etat' => 'en_attente', 'url_paiement' => $ouverture['url'],
            'reference_passerelle' => $ouverture['reference_passerelle'], 'dernier_echange' => $ouverture['reponse'],
        ]);

        $this->journal->consigner('paiement_initie', 'Ouverture : '.$paiement->libelleAudit().' sur '.$sejour->libelleAudit().'.', $sejour);

        return $paiement->refresh();
    }

    /**
     * Rappel de la passerelle. Il ne sert QU'À déclencher une vérification : son contenu n'est
     * jamais cru sur parole, même signé. C'est ce que faisait Mon Gravier, et c'est ce qui protège
     * d'un rappel forgé ou rejoué.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function traiterLeRappel(array $donnees, ?string $signature): PaiementEnLigne
    {
        if (! $this->passerelle->rappelAuthentique($donnees, $signature)) {
            $this->journal->consigner('paiement_rappel_refuse', 'Rappel de paiement refusé : signature absente ou invalide.');
            throw new ErreurMetier('Rappel non authentifié.', 'rappel_non_authentifie', 403);
        }

        $reference = $this->passerelle->referenceDuRappel($donnees);
        $paiement = $reference === null ? null : PaiementEnLigne::find($reference);
        if ($paiement === null) {
            throw new ErreurMetier('Paiement inconnu.', 'paiement_inconnu', 404);
        }

        return $this->verifier($paiement);
    }

    /**
     * Demande à la passerelle l'état RÉEL du paiement, et en tire les conséquences.
     * Idempotent : appelé dix fois, il ne produit qu'un règlement.
     */
    public function verifier(PaiementEnLigne $paiement): PaiementEnLigne
    {
        if ($paiement->etat === 'reussi') {
            return $paiement;   // déjà conclu : rien à refaire
        }

        $constat = $this->passerelle->verifier($paiement);
        $paiement->update([
            'dernier_echange' => $constat['reponse'], 'verifie_le' => now(), 'verifications' => $paiement->verifications + 1,
            'reference_passerelle' => $constat['reference_passerelle'] ?? $paiement->reference_passerelle,
        ]);

        return match ($constat['etat']) {
            'reussi' => $this->conclure($paiement, $constat),
            'echoue', 'expire' => $this->clore($paiement, $constat['etat'], 'Refusé par la passerelle.'),
            // « en attente » ou passerelle injoignable : on ne conclut rien, la reprise réessaiera.
            default => $paiement->refresh(),
        };
    }

    /** Reprise planifiée des paiements restés en attente (CdC § 13.2 : rien ne doit rester en suspens). */
    public function reprendreLesPaiementsEnAttente(): int
    {
        $limite = now()->subMinutes((int) config('paiement.expiration_minutes'));
        $repris = 0;

        foreach (PaiementEnLigne::query()->whereIn('etat', ['initie', 'en_attente'])->orderBy('created_at')->limit(200)->get() as $paiement) {
            try {
                $avant = $paiement->etat;
                $this->verifier($paiement);

                // Toujours en attente bien après le délai : la transaction est abandonnée.
                if ($paiement->refresh()->etat === $avant && $paiement->created_at?->lt($limite)) {
                    $this->clore($paiement, 'expire', 'Aucune confirmation de la passerelle dans le délai.');
                }
                $repris++;
            } catch (\Throwable $e) {
                // Un paiement en erreur n'empêche pas de traiter les suivants.
                Log::error('Reprise de paiement impossible', ['reference' => $paiement->reference, 'erreur' => $e->getMessage()]);
            }
        }

        return $repris;
    }

    /**
     * Le paiement a réussi : on crée son règlement, une seule fois.
     *
     * @param  array{etat: string, montant: int|null, mode: string|null, reference_passerelle: string|null, reponse: array<string, mixed>}  $constat
     */
    private function conclure(PaiementEnLigne $paiement, array $constat): PaiementEnLigne
    {
        // Le montant CONSTATÉ chez la passerelle prime : un client qui aurait payé moins que le dû
        // ne solde pas son séjour pour autant.
        $montant = $constat['montant'] ?? $paiement->montant;
        if ($montant !== $paiement->montant) {
            $this->journal->consigner('paiement_montant_different', sprintf(
                'Montant constaté différent pour %s : %s F attendus, %s F encaissés.',
                $paiement->reference, number_format($paiement->montant, 0, ',', ' '), number_format($montant, 0, ',', ' '),
            ), $paiement->sejour);
        }

        $reglement = DB::transaction(function () use ($paiement, $constat, $montant): ?Reglement {
            // Verrou : deux rappels simultanés, ou un rappel et une vérification, ne créent qu'un règlement.
            $verrouille = PaiementEnLigne::query()->whereKey($paiement->reference)->lockForUpdate()->first();
            if ($verrouille === null || $verrouille->reglement_id !== null) {
                return null;
            }

            $sejour = $paiement->sejour;
            $aImputer = min($montant, $this->soldes->de($sejour)['reste_du']);

            $reglement = Reglement::create([
                'sens' => 'encaissement', 'guichet' => Guichet::EnLigne,
                'agence_id' => $this->agenceDeReference(), 'tiers_id' => $paiement->client_id,
                'montant' => $montant, 'mode' => ModeDeReglement::from($constat['mode'] ?? 'carte'),
                'reference_du_mode' => $constat['reference_passerelle'] ?? $paiement->reference_passerelle,
                'notes' => 'Paiement en ligne '.$paiement->reference.' — '.$this->passerelle->nom().'.',
                'etat' => EtatDuReglement::Effectue,
                // Aucun agent n'intervient : c'est le client lui-même qui a payé.
                'saisi_par' => $paiement->client_id, 'saisi_le' => now(), 'finalise_le' => now(),
            ]);

            if ($aImputer > 0) {
                $reglement->imputations()->create(['affaire_type' => $sejour->getMorphClass(), 'affaire_id' => $sejour->id, 'montant' => $aImputer]);
            }

            $this->recus->attribuerLeNumero($reglement);
            $verrouille->update(['etat' => 'reussi', 'reglement_id' => $reglement->id, 'mode_constate' => $constat['mode'], 'motif_echec' => null]);

            return $reglement->refresh();
        });

        if ($reglement !== null) {
            $this->journal->consigner('paiement_reussi', 'Confirmé : '.$paiement->libelleAudit().' — reçu '.$reglement->numero_recu.'.', $paiement->sejour);
            // Reçu, points de fidélité, avance : le même événement que pour un règlement au guichet.
            ReglementEffectue::dispatch($reglement);
        }

        return $paiement->refresh();
    }

    private function clore(PaiementEnLigne $paiement, string $etat, string $motif): PaiementEnLigne
    {
        $paiement->update(['etat' => $etat, 'motif_echec' => $motif]);
        $this->journal->consigner('paiement_'.$etat, ucfirst($etat).' : '.$paiement->libelleAudit()." — {$motif}", $paiement->sejour);

        return $paiement->refresh();
    }

    /** Un paiement en ligne n'a pas d'agence : on le rattache à la première, pour la comptabilité. */
    private function agenceDeReference(): int
    {
        return (int) DB::table('agences')->orderBy('id')->value('id');
    }

    private function urlDeRetour(PaiementEnLigne $paiement): string
    {
        return config('plateforme.url_du_site').'/paiement/retour/'.$paiement->reference;
    }

    private function urlDeRappel(): string
    {
        return url('/api/v1/paiements/rappel');
    }
}
