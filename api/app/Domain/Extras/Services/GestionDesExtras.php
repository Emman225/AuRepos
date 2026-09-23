<?php

namespace App\Domain\Extras\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;

/**
 * Commandes d'extras (P2-EXT-01) : un client (ou, à sa place, la réception) commande un
 * extra du catalogue PENDANT un séjour déjà arrivé — même règle que
 * App\Domain\Assistance\Services\TicketsAssistance::ouvrir. Le back office confirme,
 * affecte au besoin un membre du personnel qui l'exécute, puis marque le service fait.
 *
 * Ne réinvente rien de ce que la session a déjà construit : la facturation passe par le
 * guichet d'encaissement Extras (App\Domain\Caisse\Services\Caisse::encaisserUneConsommation,
 * P2-TRF-03), jamais une ligne du net à payer figé du séjour — même choix que les repas et
 * les transferts (App\Domain\Sejours\Services\CheckOut::consommations). Aucun code secret
 * ici : un extra est exécuté en interne, jamais remis par un partenaire externe qu'il
 * faudrait authentifier (contrairement au livreur ou au chauffeur).
 */
final class GestionDesExtras
{
    public function commander(Sejour $sejour, Extra $extra, int $quantite, ?User $demandePar = null, ?string $notes = null): CommandeExtra
    {
        if ($sejour->etat !== EtatDuSejour::Arrive) {
            throw new ErreurMetier('Un extra ne se commande que pendant un séjour en cours.', 'sejour_non_arrive', 422);
        }
        if (! $extra->actif) {
            throw new ErreurMetier('Cet extra n’est plus proposé.', 'extra_inactif', 422);
        }
        if ($quantite < 1) {
            throw new ErreurMetier('La quantité doit être d’au moins un.', 'quantite_invalide', 422);
        }

        return CommandeExtra::create([
            'sejour_id' => $sejour->id, 'extra_id' => $extra->id, 'quantite' => $quantite,
            // Figés au moment T : un extra renommé ou re-tarifé ensuite ne change jamais cette commande.
            'nom_extra' => $extra->nom, 'prix_unitaire' => $extra->prix, 'montant_total' => $extra->prix * $quantite,
            'etat' => EtatDeCommandeExtra::Demande, 'demande_par_id' => $demandePar?->id, 'notes' => $notes ? trim($notes) : null,
        ])->refresh();
    }

    /** Réservé à la gestion quotidienne : vérifie et transmet la demande. */
    public function confirmer(CommandeExtra $commande): CommandeExtra
    {
        $this->exigerEtat($commande, EtatDeCommandeExtra::Demande);
        $commande->update(['etat' => EtatDeCommandeExtra::Confirmee]);

        return $commande->refresh();
    }

    /** Affecte un membre du personnel qui l'exécute — pas toutes les commandes n'en ont besoin. */
    public function affecter(CommandeExtra $commande, User $membreDuPersonnel): CommandeExtra
    {
        if ($commande->etat->estMorte() || $commande->etat === EtatDeCommandeExtra::Fournie) {
            throw new ErreurMetier('Cette commande est déjà « '.$commande->etat->libelle().' ».', 'affectation_impossible', 422);
        }

        $commande->update(['affecte_a_id' => $membreDuPersonnel->id, 'affecte_le' => now()]);

        return $commande->refresh();
    }

    /** Le service est rendu : marqué fait. */
    public function marquerFournie(CommandeExtra $commande): CommandeExtra
    {
        $this->exigerEtat($commande, EtatDeCommandeExtra::Confirmee);
        $commande->update(['etat' => EtatDeCommandeExtra::Fournie, 'fournie_le' => now()]);

        return $commande->refresh();
    }

    /** Réservé à la gestion quotidienne, motivé. */
    public function refuser(CommandeExtra $commande, string $motif): CommandeExtra
    {
        if ($commande->etat->estMorte() || $commande->etat === EtatDeCommandeExtra::Fournie) {
            throw new ErreurMetier('Cette commande est déjà « '.$commande->etat->libelle().' ».', 'commande_deja_traitee', 422);
        }

        $commande->update(['etat' => EtatDeCommandeExtra::Refusee, 'notes' => trim(($commande->notes ? $commande->notes.' — ' : '').'Refusée : '.$motif)]);

        return $commande->refresh();
    }

    /** Le client (ou la réception, motivée) annule tant que le service n'est pas rendu. */
    public function annuler(CommandeExtra $commande): CommandeExtra
    {
        if ($commande->etat->estMorte() || $commande->etat === EtatDeCommandeExtra::Fournie) {
            throw new ErreurMetier('Cette commande est déjà « '.$commande->etat->libelle().' ».', 'annulation_impossible', 422);
        }

        $commande->update(['etat' => EtatDeCommandeExtra::Annulee]);

        return $commande->refresh();
    }

    private function exigerEtat(CommandeExtra $commande, EtatDeCommandeExtra $attendu): void
    {
        if ($commande->etat !== $attendu) {
            throw new ErreurMetier(
                'Cette commande est « '.$commande->etat->libelle().' » : cette étape attend une commande « '.$attendu->libelle().' ».',
                'etape_hors_sequence',
                422,
            );
        }
    }
}
