<?php

namespace App\Domain\Repas\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Commandes de repas et boissons (CdC — « Repas et boissons ») : circuit vente→livraison
 * repris de Mon Gravier (bon de préparation, livreur, code de livraison), adapté à la
 * livraison d'une commande de restauration au logement d'un séjour EN COURS.
 *
 * Ne réinvente rien de ce que la session a déjà construit :
 *   - le code de livraison passe par App\Domain\Codes\Services\CodesSecrets (usage
 *     'livraison', déjà prévu par la contrainte de la table codes_secrets) ;
 *   - le reversement au restaurateur ou au livreur passe par
 *     App\Domain\Caisse\Services\Caisse::saisirUnDecaissement (jamais construit ici) ;
 *   - le pourcentage plateforme du restaurateur passe par
 *     App\Domain\Validation\Services\DoubleValidation (jamais construit ici).
 *
 * Aucune notification poussée (courriel/SMS/WhatsApp) n'est déclenchée ici : le modèle
 * App\Domain\Notifications\Enums\ModeleDeMessage est fermé à quatre modèles fixes, chacun
 * lié à un gabarit des Paramètres — lui ajouter un cinquième cas aurait débordé sur un
 * mécanisme existant au-delà de la réutilisation documentée. Chaque changement d'état est
 * déjà tracé par App\Domain\Audit\Concerns\EstAudite, et chaque espace self-service (client,
 * restaurateur, livreur) affiche l'état à jour : le client suit « la préparation et la
 * livraison » depuis Mes commandes, comme le demande le CdC, sans réinvention.
 */
final class GestionDesCommandes
{
    public function __construct(
        private readonly CodesSecrets $codes,
        private readonly Parametres $parametres,
    ) {}

    /**
     * @param  list<array{produit_id: int, quantite: int}>  $lignes
     * @param  bool  $offert  Extra CdC § 5.2 « premier repas livré à l'arrivée » (P4-API-08) :
     *                        chaque ligne est facturée à ZÉRO au client (prix de vente ignoré,
     *                        pas besoin d'un pourcentage plateforme déjà validé) ; le
     *                        restaurateur, lui, reste dû à son prix normal — voir
     *                        `offrirLePremierRepas` et `detteEnversLeRestaurateur`.
     */
    public function commander(Sejour $sejour, Restaurateur $restaurateur, User $client, array $lignes, string $modeReglement, ?string $notes = null, bool $offert = false): Commande
    {
        if ($sejour->client_id !== $client->id) {
            throw new ErreurMetier('Ce séjour ne vous appartient pas.', 'sejour_non_a_vous', 403);
        }
        if (! $restaurateur->actif) {
            throw new ErreurMetier('Ce restaurateur n’est plus actif.', 'restaurateur_inactif', 422);
        }
        if ($lignes === []) {
            throw new ErreurMetier('Une commande doit contenir au moins un produit.', 'commande_vide', 422);
        }

        return DB::transaction(function () use ($sejour, $restaurateur, $lignes, $modeReglement, $notes, $offert): Commande {
            $produits = Produit::query()->whereIn('id', array_column($lignes, 'produit_id'))->lockForUpdate()->get()->keyBy('id');

            $lignesAEnregistrer = [];
            $montantTotal = 0;
            foreach ($lignes as $ligne) {
                /** @var Produit|null $produit */
                $produit = $produits->get($ligne['produit_id']);
                if ($produit === null || $produit->restaurateur_id !== $restaurateur->id) {
                    throw new ErreurMetier('Un des produits demandés n’appartient pas à ce restaurateur.', 'produit_hors_carte', 422);
                }
                if (! $produit->disponible) {
                    throw new ErreurMetier('« '.$produit->nom.' » n’est plus disponible.', 'produit_indisponible', 422);
                }
                $quantite = (int) $ligne['quantite'];
                if ($quantite < 1) {
                    throw new ErreurMetier('La quantité doit être d’au moins un.', 'quantite_invalide', 422);
                }

                // Figés au moment T : un produit renommé ou re-tarifé ensuite ne change jamais cette commande.
                // Offert : zéro pour le client, quel que soit le pourcentage plateforme du restaurateur.
                $prixUnitaire = $offert ? 0 : $produit->prixDeVente();
                $montantTotal += $prixUnitaire * $quantite;
                $lignesAEnregistrer[] = [
                    'produit_id' => $produit->id, 'nom_produit' => $produit->nom,
                    'prix_unitaire_vente' => $prixUnitaire, 'quantite_commandee' => $quantite,
                ];
            }

            $commande = Commande::create([
                'sejour_id' => $sejour->id, 'restaurateur_id' => $restaurateur->id,
                'etat' => EtatDeCommande::Demande, 'mode_reglement' => $modeReglement,
                'montant_total' => $montantTotal, 'offert' => $offert, 'notes' => $notes,
            ]);
            $commande->lignes()->createMany($lignesAEnregistrer);

            return $commande->refresh()->load('lignes');
        });
    }

    /**
     * Extra CdC § 5.2 « premier repas livré à l'arrivée » (P4-API-08) : offert par
     * l'établissement, jamais facturé au client. Réservé à un séjour déjà ARRIVÉ (check-in
     * fait) — le catalogue général des extras choisis à la réservation (P2-EXT-01) n'est
     * pas construit, donc ce geste se déclenche EXPLICITEMENT depuis la réception plutôt que
     * d'inventer une règle de sélection automatique du restaurateur ou des plats offerts ;
     * `App\Domain\Sejours\Services\CheckIn` n'a donc besoin d'aucune modification.
     *
     * @param  list<array{produit_id: int, quantite: int}>  $lignes
     */
    public function offrirLePremierRepas(Sejour $sejour, Restaurateur $restaurateur, array $lignes): Commande
    {
        if ($sejour->etat !== EtatDuSejour::Arrive) {
            throw new ErreurMetier('Le premier repas offert ne se déclenche qu’après le check-in du séjour.', 'sejour_non_arrive', 422);
        }

        /** @var User $client */
        $client = $sejour->client;

        return $this->commander($sejour, $restaurateur, $client, $lignes, 'note_du_sejour', 'Premier repas offert à l’arrivée.', offert: true);
    }

    /** Réservé à la gestion quotidienne (mêmes profils que les réservations) : le règlement est vérifié. */
    public function confirmer(Commande $commande): Commande
    {
        $this->exigerEtat($commande, EtatDeCommande::Demande);
        $commande->update(['etat' => EtatDeCommande::Confirmee]);

        return $commande->refresh();
    }

    /** Réservé au restaurateur titulaire de la commande. */
    public function demarrerPreparation(Commande $commande, Restaurateur $restaurateurConnecte): Commande
    {
        $this->exigerTitulaire($commande, $restaurateurConnecte);
        $this->exigerEtat($commande, EtatDeCommande::Confirmee);

        $commande->update(['etat' => EtatDeCommande::EnPreparation]);

        return $commande->refresh();
    }

    /**
     * Le restaurateur saisit le bon de préparation : la quantité RÉELLEMENT servie par
     * ligne, qui peut différer de la quantité commandée.
     *
     * @param  array<int, int>  $quantitesServies  [ligne_id => quantité servie]
     */
    public function marquerPrete(Commande $commande, Restaurateur $restaurateurConnecte, array $quantitesServies): Commande
    {
        $this->exigerTitulaire($commande, $restaurateurConnecte);
        $this->exigerEtat($commande, EtatDeCommande::EnPreparation);

        DB::transaction(function () use ($commande, $quantitesServies): void {
            foreach ($commande->lignes as $ligne) {
                if (! array_key_exists($ligne->id, $quantitesServies)) {
                    throw new ErreurMetier('La quantité servie de chaque ligne doit être renseignée.', 'quantite_servie_manquante', 422);
                }
                $quantite = (int) $quantitesServies[$ligne->id];
                if ($quantite < 0) {
                    throw new ErreurMetier('Une quantité servie ne peut pas être négative.', 'quantite_invalide', 422);
                }
                $ligne->update(['quantite_servie' => $quantite]);
            }
            $commande->update(['etat' => EtatDeCommande::Prete]);
        });

        return $commande->refresh()->load('lignes');
    }

    /** Réservé à la gestion quotidienne : affecte un livreur, saisit sa rémunération, émet le code de livraison. */
    public function affecterUnLivreur(Commande $commande, Livreur $livreur, int $remunerationLivreur): Commande
    {
        $this->exigerEtat($commande, EtatDeCommande::Prete);
        if (! $livreur->actif) {
            throw new ErreurMetier('Ce livreur n’est plus actif.', 'livreur_inactif', 422);
        }
        if ($remunerationLivreur < 0) {
            throw new ErreurMetier('La rémunération du livreur ne peut pas être négative.', 'montant_invalide', 422);
        }

        return DB::transaction(function () use ($commande, $livreur, $remunerationLivreur): Commande {
            $commande->update([
                'etat' => EtatDeCommande::EnLivraison, 'livreur_id' => $livreur->id, 'remuneration_livreur' => $remunerationLivreur,
            ]);
            $this->codes->generer($commande, 'livraison');

            return $commande->refresh();
        });
    }

    /** Le livreur SAISIT le code que le client lui remet ; il ne le lit jamais à l’avance. */
    public function cloturerParCode(Commande $commande, string $codeSaisi, User $livreurConnecte): Commande
    {
        $this->exigerEtat($commande, EtatDeCommande::EnLivraison);

        $livreur = Livreur::query()->where('user_id', $livreurConnecte->id)->first();
        if ($livreur === null || $commande->livreur_id !== $livreur->id) {
            throw new ErreurMetier('Cette course ne vous est pas affectée.', 'livreur_non_affecte', 403);
        }

        $this->codes->verifier($commande, 'livraison', $codeSaisi, $livreurConnecte);
        $commande->update(['etat' => EtatDeCommande::Livree]);

        return $commande->refresh();
    }

    /** Réservé à la gestion quotidienne ou au restaurateur (motivé). */
    public function refuser(Commande $commande, string $motif): Commande
    {
        if (in_array($commande->etat, [EtatDeCommande::Livree, EtatDeCommande::Annulee, EtatDeCommande::Refusee], true)) {
            throw new ErreurMetier('Cette commande est déjà '.mb_strtolower($commande->etat->libelle()).' : elle ne peut plus être refusée.', 'commande_deja_traitee', 422);
        }

        $commande->update(['etat' => EtatDeCommande::Refusee, 'notes' => trim(($commande->notes ? $commande->notes.' — ' : '').'Refusée : '.$motif)]);

        return $commande->refresh();
    }

    /**
     * Ce qui est dû au restaurateur (CdC — « Dette › Restaurateurs : quantités servies ×
     * prix restaurateur, TVA en plus s'il est assujetti »).
     *
     * Seules les commandes LIVRÉES entrent dans le calcul : une commande seulement « prête »
     * peut encore être refusée ou annulée avant livraison, et ses quantités servies ne sont
     * pas encore définitivement acquises tant que le circuit n'est pas allé à son terme
     * (choix le plus défendable ; voir le rapport de la tâche).
     *
     * @return array{du: int, deja_verse: int}
     */
    public function detteEnversLeRestaurateur(Restaurateur $restaurateur): array
    {
        $commandes = Commande::query()
            ->where('restaurateur_id', $restaurateur->id)->where('etat', EtatDeCommande::Livree)
            ->with('lignes.produit')->get();

        $tauxTva = $restaurateur->assujetti_tva ? (float) $this->parametres->valeur('taxes.tva') : 0.0;

        $du = 0;
        foreach ($commandes as $commande) {
            foreach ($commande->lignes as $ligne) {
                $quantite = $ligne->quantite_servie ?? $ligne->quantite_commandee;
                $prixAchat = $ligne->produit?->prix_restaurateur ?? 0;
                $montantLigne = $quantite * $prixAchat;
                $du += (int) round($montantLigne * (1 + $tauxTva / 100));
            }
        }

        $dejaVerse = (int) Reglement::query()
            ->where('tiers_id', $restaurateur->user_id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->sum('montant');

        return ['du' => max(0, $du - $dejaVerse), 'deja_verse' => $dejaVerse];
    }

    /**
     * Gains du livreur (même logique que le chauffeur des transferts) : total gagné sur ses
     * courses livrées, moins ce qui lui a déjà été décaissé.
     *
     * @return array{total_gagne: int, deja_verse: int, solde_du: int}
     */
    public function gainsDuLivreur(Livreur $livreur): array
    {
        $totalGagne = (int) Commande::query()
            ->where('livreur_id', $livreur->id)->where('etat', EtatDeCommande::Livree)
            ->sum('remuneration_livreur');

        $dejaVerse = (int) Reglement::query()
            ->where('tiers_id', $livreur->user_id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->sum('montant');

        return ['total_gagne' => $totalGagne, 'deja_verse' => $dejaVerse, 'solde_du' => max(0, $totalGagne - $dejaVerse)];
    }

    private function exigerTitulaire(Commande $commande, Restaurateur $restaurateurConnecte): void
    {
        if ($commande->restaurateur_id !== $restaurateurConnecte->id) {
            throw new ErreurMetier('Cette commande ne vous appartient pas.', 'commande_non_a_vous', 403);
        }
    }

    private function exigerEtat(Commande $commande, EtatDeCommande $attendu): void
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
