<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDeLaDemandeAnnulation;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\DemandeAnnulation;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Demandes d'annulation (P2-SEJ-06, CdC § 6.1) : un séjour CONFIRMÉ (ou déjà arrivé) ne
 * s'annule plus d'un geste du client — celui-ci DEMANDE, un administrateur INSTRUIT.
 *
 * Retenu / remboursable : la MÊME formule que toute annulation
 * (`CycleDuSejour::montantRetenuSiAnnulation`, jamais un second calcul). Le remboursement est
 * un décaissement (`Caisse::saisirUnDecaissement`, déjà prévu « pour les remboursements » dans
 * le commentaire de la toute première migration de la caisse) — réservé aux administrateurs,
 * comme tout décaissement dans ce projet.
 *
 * DÉLIBÉRÉMENT ABSENTS (hors périmètre, cf. PLAN-REALISATION.md) :
 *   - la reprise de la commission apporteur au prorata (déjà différée ailleurs, même motif :
 *     aucune règle de calcul n'est écrite dans le CdC) ;
 *   - l'émission automatique d'un avoir DGI : le mécanisme existe déjà
 *     (`Fiscalite\Services\Factures::emettreUnAvoir`) mais il annule une facture ENTIÈRE,
 *     jamais une part proportionnelle à la retenue — la plupart des annulations n'ont d'ailleurs
 *     encore qu'une proforma (jamais transmise) à ce stade. Un administrateur émet l'avoir à la
 *     main, depuis l'écran Factures déjà existant, si une vraie facture a déjà été transmise.
 */
final class DemandesDAnnulation
{
    public function __construct(
        private readonly CycleDuSejour $cycle,
        private readonly SoldeDesSejours $soldes,
        private readonly Caisse $caisse,
    ) {}

    public function demander(Sejour $sejour, User $client, string $motif): DemandeAnnulation
    {
        if (! in_array($sejour->etat, [EtatDuSejour::Confirme, EtatDuSejour::Arrive], true)) {
            throw new ErreurMetier(
                'Une demande d’annulation ne se fait que sur un séjour confirmé ou arrivé.',
                'sejour_non_annulable',
                422,
            );
        }
        if (DemandeAnnulation::query()->where('sejour_id', $sejour->id)->where('etat', EtatDeLaDemandeAnnulation::EnAttente->value)->exists()) {
            throw new ErreurMetier('Une demande d’annulation est déjà en cours pour ce séjour.', 'demande_deja_en_cours', 422);
        }

        return DemandeAnnulation::create([
            'sejour_id' => $sejour->id, 'demandee_par' => $client->id, 'motif_client' => trim($motif),
        ])->refresh();
    }

    /** Rejette la demande : le séjour continue, rien ne change pour lui. */
    public function rejeter(DemandeAnnulation $demande, User $administrateur, string $motif): DemandeAnnulation
    {
        $this->exigerEnAttente($demande);

        $demande->update([
            'etat' => EtatDeLaDemandeAnnulation::Rejetee, 'motif_decision' => trim($motif),
            'instruite_par' => $administrateur->id, 'instruite_le' => now(),
        ]);

        return $demande->refresh();
    }

    /**
     * Accepte la demande : annule le séjour (même circuit que toute annulation — retenue,
     * points repris), puis rembourse ce qui reste par décaissement, si le client a versé
     * plus que la retenue.
     */
    public function accepter(DemandeAnnulation $demande, User $administrateur, ?ModeDeReglement $modeDeRemboursement = null, ?string $motif = null): DemandeAnnulation
    {
        $this->exigerEnAttente($demande);
        $sejour = $demande->sejour;

        $retenu = $this->cycle->montantRetenuSiAnnulation($sejour);
        $encaisse = $this->soldes->de($sejour)['encaisse_hors_avance'];
        $rembourse = max(0, $encaisse - $retenu);

        if ($rembourse > 0 && $modeDeRemboursement === null) {
            throw new ErreurMetier('Indiquez le mode de règlement du remboursement.', 'mode_de_remboursement_obligatoire', 422);
        }

        return DB::transaction(function () use ($demande, $sejour, $administrateur, $modeDeRemboursement, $motif, $retenu, $rembourse): DemandeAnnulation {
            $this->cycle->annuler($sejour, $administrateur, $motif ?: 'Annulation instruite : demande du client acceptée.');

            $reglement = null;
            if ($rembourse > 0) {
                $reglement = $this->caisse->saisirUnDecaissement(
                    $administrateur, $sejour->client, $rembourse, $modeDeRemboursement,
                    'Remboursement sur annulation instruite du séjour '.$sejour->reference,
                    Guichet::Remboursements,
                );
            }

            $demande->update([
                'etat' => EtatDeLaDemandeAnnulation::Acceptee, 'montant_retenu' => $retenu, 'montant_rembourse' => $rembourse,
                'reglement_id' => $reglement?->id, 'motif_decision' => $motif ? trim($motif) : null,
                'instruite_par' => $administrateur->id, 'instruite_le' => now(),
            ]);

            return $demande->refresh();
        });
    }

    private function exigerEnAttente(DemandeAnnulation $demande): void
    {
        if (! $demande->enAttente()) {
            throw new ErreurMetier('Cette demande est déjà « '.$demande->etat->libelle().' ».', 'demande_deja_instruite', 422);
        }
    }
}
