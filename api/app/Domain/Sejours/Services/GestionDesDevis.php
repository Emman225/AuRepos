<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Devis;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Devis (CdC § 5.1) : « un client peut faire établir un devis de séjour (prix figés par le
 * serveur) et le transformer en réservation d'un clic ; un devis en attente se supprime
 * (archivé, jamais effacé). »
 *
 * N'occupe PAS le calendrier à l'établissement : seule la transformation réserve les dates,
 * et c'est la base — pas ce service — qui garantit alors la disponibilité. Le prix, lui,
 * ne bouge plus jamais entre les deux, quoi qu'il arrive aux Paramètres entre-temps.
 */
final class GestionDesDevis
{
    public function __construct(
        private readonly CalculComplet $calculComplet,
        private readonly ReservationDeSejour $reservation,
    ) {}

    /**
     * @param  array{arrivee: string, depart: string, adultes: int, enfants?: int, arrivee_tardive?: bool, depart_tardif?: bool,
     *               heure_arrivee_prevue?: string|null, points_utilises?: int, code_promo?: string|null}  $saisie
     */
    public function etablir(User $utilisateur, Logement $logement, array $saisie, ?User $creePar = null): Devis
    {
        $client = Client::de($utilisateur);
        // Même contrôle que pour une réservation directe : un devis ne se fait pas sur un logement fermé.
        $this->reservation->controler($logement, $client, ['adultes' => $saisie['adultes'], 'enfants' => $saisie['enfants'] ?? 0]);

        $resultat = $this->calculComplet->pour($utilisateur, $client, $logement, $saisie);
        $devis = $resultat->devis;

        return Devis::create([
            'logement_id' => $logement->id, 'client_id' => $utilisateur->id,
            'arrivee' => $saisie['arrivee'], 'depart' => $saisie['depart'],
            'adultes' => (int) $saisie['adultes'], 'enfants' => (int) ($saisie['enfants'] ?? 0),
            'arrivee_tardive' => (bool) ($saisie['arrivee_tardive'] ?? false), 'depart_tardif' => (bool) ($saisie['depart_tardif'] ?? false),
            'heure_arrivee_prevue' => $saisie['heure_arrivee_prevue'] ?? null,
            'code_promo' => $resultat->codePromo?->code,
            'devis' => $devis->toArray(), 'net_a_payer' => $devis->netAPayer, 'caution' => $devis->caution,
            'points_utilises' => $resultat->points['utilisables'], 'reduction_points' => $resultat->points['valeur'],
            'cree_par' => $creePar?->id,
        ])->refresh();
    }

    /**
     * Transforme un devis en réservation « d'un clic » : le prix ne se recalcule PAS, il
     * n'a jamais cessé d'être celui figé à l'établissement. Seules la disponibilité (le
     * devis ne tenait pas les dates) et l'ouverture du logement sont revérifiées MAINTENANT.
     *
     * @param  array{mode_reglement: string, bon_de_commande?: string|null, occupants?: list<array<string, mixed>>}  $saisie
     */
    public function transformer(Devis $devis, array $saisie, string $canal = 'direct', ?User $creePar = null): Sejour
    {
        if (! $devis->enAttente()) {
            throw new ErreurMetier('Ce devis a déjà été '.($devis->etat === 'transforme' ? 'transformé en réservation' : 'archivé').'.', 'devis_non_disponible', 422);
        }

        $logement = $devis->logement;
        $utilisateur = $devis->client;
        $client = Client::de($utilisateur);

        // Le logement a pu fermer depuis l'établissement du devis ; le PRIX, lui, ne bouge pas.
        // adultes/enfants viennent du devis figé : le contrôle de cohérence des occupants les vérifie contre CE nombre-là.
        $this->reservation->controler($logement, $client, [...$saisie, 'adultes' => $devis->adultes, 'enfants' => $devis->enfants]);
        $this->reservation->controlerLeReglement($client, $saisie['mode_reglement'], $devis->net_a_payer);

        $sejour = $this->reservation->creerLeSejour(
            $utilisateur, $logement,
            [
                'arrivee' => $devis->arrivee->toDateString(), 'depart' => $devis->depart->toDateString(),
                'adultes' => $devis->adultes, 'enfants' => $devis->enfants,
                'mode_reglement' => $saisie['mode_reglement'], 'heure_arrivee_prevue' => $devis->heure_arrivee_prevue,
                'bon_de_commande' => $saisie['bon_de_commande'] ?? null, 'occupants' => $saisie['occupants'] ?? [],
            ],
            $devis->devis, $devis->net_a_payer, $devis->caution,
            $devis->points_utilises, $devis->reduction_points, $canal, $creePar,
        );

        DB::transaction(fn () => $devis->update(['etat' => 'transforme', 'sejour_id' => $sejour->id]));

        return $sejour;
    }

    /** « Se supprime » (CdC § 5.1) : archivé, jamais effacé. */
    public function archiver(Devis $devis): void
    {
        if (! $devis->enAttente()) {
            throw new ErreurMetier('Seul un devis en attente peut être archivé.', 'devis_non_disponible', 422);
        }

        $devis->update(['etat' => 'archive']);
    }
}
