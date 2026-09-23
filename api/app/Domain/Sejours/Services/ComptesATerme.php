<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Imputation;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;

/**
 * Client ordinaire / client à terme (CdC § 5.1, 5.3).
 *
 * Un client ordinaire règle avant l'arrivée, ou au plus tard au check-in. Un client à terme —
 * entreprise, ONG, administration, ambassade — dispose d'une ligne de crédit : ses collaborateurs
 * séjournent d'abord, la facture est réglée ensuite. Le plafond de crédit additionne le reste dû
 * sur ses séjours (les extras y sont déjà inclus ; les transferts factureront de même à leur
 * arrivée en lot 2, sans rien changer ici). Un plafond à zéro signifie « aucune limite ».
 */
final class ComptesATerme
{
    /** Pièces exigées avant d'accepter un dossier (CdC § 5.3 : « RCCM, bilan, pièce d'identité obligatoires »). */
    private const PIECES_OBLIGATOIRES = [TypeDePiece::Rccm, TypeDePiece::Bilan, TypeDePiece::PieceIdentite];

    /** Le client dépose sa demande : rien n'est encore décidé, mais elle apparaît dans la file du back-office. */
    public function demander(User $utilisateur): Client
    {
        $client = Client::de($utilisateur);

        if (! $client->doitFournirUnBonDeCommande()) {
            throw new ErreurMetier(
                'Le règlement à terme est réservé aux organisations (entreprise, ONG, administration, ambassade).',
                'a_terme_reserve_aux_organisations',
                422,
            );
        }

        if ($client->statut_a_terme === StatutDemandeATerme::Acceptee) {
            throw new ErreurMetier('Ce compte dispose déjà d’une ligne de crédit.', 'a_terme_deja_accepte', 422);
        }
        if ($client->statut_a_terme === StatutDemandeATerme::EnAttente) {
            throw new ErreurMetier('Une demande est déjà en cours d’instruction.', 'a_terme_deja_en_attente', 422);
        }

        $client->update([
            'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now(),
            'a_terme_motif_refus' => null, 'a_terme_traite_par' => null, 'a_terme_traite_le' => null,
        ]);

        return $client->refresh();
    }

    /** Accepte le dossier et fixe le plafond de crédit ; exige les trois pièces validées (CdC § 5.3). */
    public function accepter(Client $client, int $plafondCredit, User $administrateur): Client
    {
        if ($client->statut_a_terme !== StatutDemandeATerme::EnAttente) {
            throw new ErreurMetier('Il n’y a pas de demande en attente pour ce client.', 'a_terme_aucune_demande', 422);
        }
        if ($plafondCredit < 0) {
            throw new ErreurMetier('Le plafond de crédit ne peut pas être négatif.', 'plafond_credit_invalide', 422);
        }

        $manquantes = $this->piecesManquantes($client);
        if ($manquantes !== []) {
            throw new ErreurMetier(
                'Dossier incomplet : il manque '.implode(', ', array_map(fn (TypeDePiece $t): string => $t->libelle(), $manquantes)).' validé(e)(s).',
                'a_terme_dossier_incomplet',
                422,
            );
        }

        $client->update([
            'statut_a_terme' => StatutDemandeATerme::Acceptee, 'plafond_credit' => $plafondCredit,
            'a_terme_motif_refus' => null, 'a_terme_traite_par' => $administrateur->id, 'a_terme_traite_le' => now(),
        ]);

        return $client->refresh();
    }

    public function refuser(Client $client, string $motif, User $administrateur): Client
    {
        if ($client->statut_a_terme !== StatutDemandeATerme::EnAttente) {
            throw new ErreurMetier('Il n’y a pas de demande en attente pour ce client.', 'a_terme_aucune_demande', 422);
        }

        $client->update([
            'statut_a_terme' => StatutDemandeATerme::Refusee, 'a_terme_motif_refus' => $motif,
            'a_terme_traite_par' => $administrateur->id, 'a_terme_traite_le' => now(),
        ]);

        return $client->refresh();
    }

    /**
     * Vérifie qu'une réservation à terme est possible et ne dépasse pas le plafond ; sinon
     * refuse avec le détail du calcul (CdC § 5.1 : « refusée avec le détail du calcul »).
     */
    public function verifierEligibiliteEtPlafond(Client $client, int $montantSupplementaire): void
    {
        if (! $client->estATerme()) {
            throw new ErreurMetier('Le règlement à terme est réservé aux clients disposant d’une ligne de crédit.', 'client_non_a_terme', 422);
        }

        if ($client->plafond_credit <= 0) {
            return; // 0 = aucune limite (CdC § 5.1)
        }

        $encoursActuel = $this->encours($client);
        $total = $encoursActuel + $montantSupplementaire;

        if ($total > $client->plafond_credit) {
            throw new ErreurMetier(sprintf(
                'Cette réservation porterait l’encours à %s F CFA, au-delà du plafond de crédit de %s F CFA (encours actuel : %s F CFA, réservation demandée : %s F CFA).',
                number_format($total, 0, ',', ' '), number_format($client->plafond_credit, 0, ',', ' '),
                number_format($encoursActuel, 0, ',', ' '), number_format($montantSupplementaire, 0, ',', ' '),
            ), 'plafond_de_credit_depasse', 422);
        }
    }

    /**
     * Somme du reste dû sur tous les séjours actifs du client (annulés et no-show exclus).
     * Les extras sont déjà dans le net à payer de chaque séjour ; les transferts s'y ajouteront
     * en lot 2 par le même mécanisme d'imputation, sans rien changer ici.
     */
    public function encours(Client $client): int
    {
        $sejours = Sejour::query()
            ->where('client_id', $client->user_id)
            ->whereNotIn('etat', [EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->get(['id', 'net_a_payer']);

        if ($sejours->isEmpty()) {
            return 0;
        }

        $etatsQuiReservent = array_map(
            fn (EtatDuReglement $e): string => $e->value,
            array_filter(EtatDuReglement::cases(), fn (EtatDuReglement $e): bool => $e === EtatDuReglement::Effectue || $e->enCours()),
        );

        $regle = Imputation::query()
            ->join('reglements', 'reglements.id', '=', 'imputations_reglement.reglement_id')
            ->where('imputations_reglement.affaire_type', (new Sejour)->getMorphClass())
            ->whereIn('imputations_reglement.affaire_id', $sejours->pluck('id'))
            ->where('reglements.sens', 'encaissement')
            ->whereIn('reglements.etat', $etatsQuiReservent)
            ->groupBy('imputations_reglement.affaire_id')
            ->selectRaw('imputations_reglement.affaire_id as sejour_id, sum(imputations_reglement.montant) as total')
            ->pluck('total', 'sejour_id');

        return (int) $sejours->sum(fn (Sejour $s): int => max(0, $s->net_a_payer - (int) ($regle[$s->id] ?? 0)));
    }

    /** @return array<int, TypeDePiece> */
    private function piecesManquantes(Client $client): array
    {
        $valables = $client->pieces->filter(fn ($p): bool => $p->statut === StatutDePiece::Validee && $p->estValable())->pluck('type');

        return array_values(array_filter(self::PIECES_OBLIGATOIRES, fn (TypeDePiece $t): bool => ! $valables->contains($t)));
    }
}
