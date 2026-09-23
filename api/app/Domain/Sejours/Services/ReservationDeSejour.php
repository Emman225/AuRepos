<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Caisse\Services\Avances;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Services\Factures;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une réservation (CdC § 5.2). Tout se joue dans UNE transaction :
 * le prix est recalculé par le serveur, le séjour est créé avec ses valeurs figées,
 * et les dates sont prises — ou rien de tout cela n'existe.
 */
final class ReservationDeSejour
{
    public function __construct(
        private readonly Calendrier $calendrier,
        private readonly Parametres $parametres,
        private readonly Avances $avances,
        private readonly PointsDeFidelite $points,
        private readonly ComptesATerme $comptesATerme,
        private readonly CalculComplet $calculComplet,
        private readonly Factures $factures,
    ) {}

    /**
     * @param  array{arrivee: string, depart: string, adultes: int, enfants?: int, arrivee_tardive?: bool, depart_tardif?: bool,
     *               mode_reglement: string, heure_arrivee_prevue?: string|null, bon_de_commande?: string|null, code_promo?: string|null,
     *               occupants?: list<array<string, mixed>>}  $saisie
     */
    public function reserver(User $utilisateur, Logement $logement, array $saisie, string $canal = 'direct', ?User $creePar = null): Sejour
    {
        $client = Client::de($utilisateur);
        $this->controler($logement, $client, $saisie);

        $resultat = $this->calculComplet->pour($utilisateur, $client, $logement, $saisie);
        $devis = $resultat->devis;
        $points = $resultat->points;

        $this->controlerLeReglement($client, $saisie['mode_reglement'], $devis->netAPayer);

        return $this->creerLeSejour(
            $utilisateur, $logement, $saisie, $devis->toArray(), $devis->netAPayer, $devis->caution,
            $points['utilisables'], $points['valeur'], $canal, $creePar,
        );
    }

    /**
     * Crée le séjour à partir d'un devis DÉJÀ FIGÉ — le sien, ou celui d'un devis autonome
     * transformé (CdC § 5.1) : dans les deux cas, ce sont les MÊMES étapes (occuper le
     * calendrier, utiliser les points, imputer l'avance), rien n'est recalculé ici.
     *
     * @param  array{arrivee: string, depart: string, adultes: int, enfants?: int, mode_reglement: string,
     *               heure_arrivee_prevue?: string|null, bon_de_commande?: string|null, occupants?: list<array<string, mixed>>}  $saisie
     * @param  array<string, mixed>  $devisFige
     */
    public function creerLeSejour(
        User $utilisateur, Logement $logement, array $saisie, array $devisFige, int $netAPayer, int $caution,
        int $pointsUtilises, int $reductionPoints, string $canal, ?User $creePar,
    ): Sejour {
        $politique = $logement->politique_annulation;

        return DB::transaction(function () use ($utilisateur, $logement, $saisie, $canal, $creePar, $devisFige, $netAPayer, $caution, $pointsUtilises, $reductionPoints, $politique): Sejour {
            $sejour = Sejour::create([
                'logement_id' => $logement->id,
                'client_id' => $utilisateur->id,
                'arrivee' => $saisie['arrivee'],
                'depart' => $saisie['depart'],
                'adultes' => (int) $saisie['adultes'],
                'enfants' => (int) ($saisie['enfants'] ?? 0),
                'canal' => $canal,
                'mode_reglement' => $saisie['mode_reglement'],
                'heure_arrivee_prevue' => $saisie['heure_arrivee_prevue'] ?? null,
                'bon_de_commande' => $saisie['bon_de_commande'] ?? null,
                'cree_par' => $creePar?->id,

                // --- Valeurs FIGÉES : plus aucun paramètre ne les touchera (CdC § 5.4) ---
                'devis' => $devisFige,
                'net_a_payer' => $netAPayer,
                'caution' => $caution,
                'points_utilises' => $pointsUtilises,
                'reduction_points' => $reductionPoints,
                // Base du futur reversement au propriétaire ; jamais montré au client.
                'prix_proprietaire_par_nuit' => $logement->prix_proprietaire,
                'acompte_exige' => CalculDuSejour::pourcentage($netAPayer, (float) $this->parametres->valeur('sejours.taux_acompte')),
                'politique_annulation' => $politique,
                'annulation_pourcentage_retenu' => (float) $this->parametres->valeur('sejours.annulation_'.$politique->value),
                'annulation_delai_jours' => (int) $this->parametres->valeur('sejours.annulation_'.$politique->value.'_delai'),

                // Une demande non réglée libère ses dates après N heures. Le client à terme, lui, règle après le séjour.
                'expire_le' => $saisie['mode_reglement'] === 'a_terme'
                    ? null
                    : now()->addHours((int) $this->parametres->valeur('sejours.delai_expiration_heures')),
            ]);

            foreach ($saisie['occupants'] ?? [] as $occupant) {
                $sejour->occupants()->create($occupant);
            }

            // C'est ICI que la disponibilité est garantie : la base refuse si une autre réservation a pris ces nuits.
            $this->calendrier->occuper($sejour);

            $this->points->utiliserSur($sejour, $pointsUtilises);

            // Un séjour réglé hors ligne se déduit de lui-même de l'avance du client, du dépôt le plus ancien au plus récent (CdC § 4).
            if ($saisie['mode_reglement'] === 'agence') {
                $this->avances->imputerSur($sejour, $creePar ?? $utilisateur);
            }

            // Proforma à la réservation (P1-FNE-02, CdC § 9.4) : un document sans valeur fiscale,
            // jamais transmis à la DGI ici — seule une vraie facture l'est, plus tard, manuellement
            // ou à l'encaissement. Générée dans la MÊME transaction : pas de séjour sans sa proforma.
            $this->factures->genererPourUnSejour($sejour, TypeDeFacture::Proforma, $creePar ?? $utilisateur);

            return $sejour->refresh();
        });
    }

    public function controlerLeReglement(Client $client, string $mode, int $netAPayer): void
    {
        if ($mode === 'a_terme') {
            // Éligibilité ET plafond, avec le détail du calcul en cas de refus (CdC § 5.1).
            $this->comptesATerme->verifierEligibiliteEtPlafond($client, $netAPayer);
        }

        if ($mode === 'en_ligne') {
            if (! $this->parametres->valeur('comptant.paiement_en_ligne_actif')) {
                throw new ErreurMetier('Le paiement en ligne n’est pas disponible pour le moment : choisissez le règlement en agence.', 'paiement_en_ligne_indisponible', 422);
            }
            $plafond = (int) $this->parametres->valeur('general.plafond_paiement_en_ligne');
            if ($plafond > 0 && $netAPayer > $plafond) {
                throw new ErreurMetier(
                    'Ce séjour dépasse le plafond du paiement en ligne ('.number_format($plafond, 0, ',', ' ').' F) : réglez en agence ou par virement.',
                    'plafond_paiement_en_ligne',
                    422,
                );
            }
        }
    }

    /** @param array<string, mixed> $saisie */
    public function controler(Logement $logement, Client $client, array $saisie): void
    {
        if ($client->liste_noire) {
            throw new ErreurMetier('Ce compte ne peut plus réserver : contactez l’agence.', 'client_liste_noire', 422);
        }

        $residence = $logement->residence;
        if ($logement->etat_publication !== EtatPublication::Publie || ! $residence->active || $residence->disponibilite !== Disponibilite::Disponible) {
            throw new ErreurMetier('Ce logement n’est pas ouvert à la réservation.', 'logement_non_reservable', 422);
        }

        if ($client->doitFournirUnBonDeCommande() && blank($saisie['bon_de_commande'] ?? null)) {
            throw new ErreurMetier('Indiquez le numéro de votre bon de commande interne : il est obligatoire pour une organisation.', 'bon_de_commande_obligatoire', 422);
        }

        $nombre = count($saisie['occupants'] ?? []);
        $attendu = (int) $saisie['adultes'] + (int) ($saisie['enfants'] ?? 0);
        if ($nombre > 0 && $nombre !== $attendu) {
            throw new ErreurMetier("Vous annoncez {$attendu} occupant(s) mais en décrivez {$nombre}.", 'occupants_incoherents', 422);
        }
    }
}
