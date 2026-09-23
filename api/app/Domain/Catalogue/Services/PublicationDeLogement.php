<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Support\Api\ErreurMetier;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Cycle de publication d'un logement (CdC § 7.1, logigramme 6), côté administration :
 *
 *   brouillon ──soumettre──▶ en attente ──publier──▶ publié ◀──réactiver── suspendu
 *        ▲                        │                     └────suspendre────────▲
 *        └──────(corriger)──── refusé ◀──refuser
 *
 * Règle reprise de la double validation : celui qui a SAISI un logement au nom d'un
 * propriétaire (tiers ou interne) ne le publie pas lui-même.
 * Le circuit vu du propriétaire (motif par photo, négociation en ligne) arrive avec P3-PUB.
 */
final class PublicationDeLogement
{
    public function __construct(
        private readonly Parametres $parametres,
        private readonly JournalAudit $journal,
    ) {}

    public function soumettre(Logement $logement, User $auteur): void
    {
        $this->exigerEtat($logement, EtatPublication::Brouillon, EtatPublication::Refuse);
        $this->exigerPret($logement, prixExiges: false);

        $this->passer($logement, EtatPublication::EnAttente, $auteur, 'Soumission à la validation', ['motif_refus' => null]);
    }

    public function publier(Logement $logement, User $validateur): void
    {
        $this->exigerEtat($logement, EtatPublication::EnAttente);
        $this->exigerLeValidateur($validateur);

        if ($logement->getAttribute('cree_par') === $validateur->id) {
            throw new ErreurMetier(
                'Vous avez saisi ce logement : un autre administrateur doit le publier.',
                'validation_de_sa_propre_saisie',
                403,
            );
        }
        $this->exigerPret($logement, prixExiges: true);

        $this->passer($logement, EtatPublication::Publie, $validateur, 'Publication', [
            'publie_le' => now(), 'publie_par' => $validateur->id, 'motif_refus' => null,
        ]);
    }

    public function refuser(Logement $logement, User $validateur, string $motif): void
    {
        $this->exigerEtat($logement, EtatPublication::EnAttente);
        $this->exigerLeValidateur($validateur);

        $this->passer($logement, EtatPublication::Refuse, $validateur, "Refus de publication — {$motif}", ['motif_refus' => $motif]);
    }

    /** Retrait temporaire (travaux, litige) ; les séjours confirmés restent honorés. */
    public function suspendre(Logement $logement, User $auteur, string $motif): void
    {
        $this->exigerEtat($logement, EtatPublication::Publie);
        $this->exigerUnAdministrateur($auteur);

        $this->passer($logement, EtatPublication::Suspendu, $auteur, "Suspension — {$motif}", ['motif_refus' => $motif]);
    }

    public function reactiver(Logement $logement, User $auteur): void
    {
        $this->exigerEtat($logement, EtatPublication::Suspendu);
        $this->exigerUnAdministrateur($auteur);
        $this->exigerPret($logement, prixExiges: true);

        $this->passer($logement, EtatPublication::Publie, $auteur, 'Remise en ligne', ['motif_refus' => null]);
    }

    /**
     * Ce qui empêche la mise en ligne, en clair.
     *
     * @return list<string>
     */
    public function obstacles(Logement $logement, bool $prixExiges = true): array
    {
        $minimum = (int) $this->parametres->valeur('proprietaires.photos_minimum');
        $photos = $logement->photos()->where('etat', 'acceptee')->count();
        $proprietaire = $logement->residence->proprietaire;
        // Le prix propriétaire n'a pas de sens pour le compte interne ni en mode commission.
        $prixProprietaireAttendu = ! $proprietaire->interne && $proprietaire->mode_remuneration === ModeDeRemuneration::PrixNegocie;

        return array_values(array_filter([
            $photos >= $minimum ? null : "Il faut au moins {$minimum} photos acceptées (il y en a {$photos}).",
            $logement->photos()->where('couverture', true)->exists() || $photos === 0 ? null : 'Aucune photo de couverture n’est désignée.',
            ! $prixExiges || $logement->prix_vente !== null ? null : 'Le prix de vente n’est pas encore validé.',
            ! $prixExiges || ! $prixProprietaireAttendu || $logement->prix_proprietaire !== null ? null : 'Le prix propriétaire n’est pas encore arrêté.',
            $logement->residence->active ? null : 'La résidence est désactivée.',
        ]));
    }

    private function exigerPret(Logement $logement, bool $prixExiges): void
    {
        $obstacles = $this->obstacles($logement, $prixExiges);
        if ($obstacles !== []) {
            throw new ErreurMetier(implode(' ', $obstacles), 'logement_non_pret', 422);
        }
    }

    private function exigerEtat(Logement $logement, EtatPublication ...$attendus): void
    {
        if (! in_array($logement->etat_publication, $attendus, true)) {
            throw new ErreurMetier(
                'Cette action n’est pas possible pour un logement « '.$logement->etat_publication->libelle().' ».',
                'etat_de_publication_incompatible',
            );
        }
    }

    /** Si un validateur des publications est désigné dans les Paramètres, lui seul publie ou refuse. */
    private function exigerLeValidateur(User $utilisateur): void
    {
        $this->exigerUnAdministrateur($utilisateur);
        $designe = $this->parametres->valeur('proprietaires.validateur_publications_id');

        if ($designe !== null && $designe !== $utilisateur->id) {
            throw new ErreurMetier(
                'Seul le validateur des publications désigné dans les Paramètres peut publier ou refuser un logement.',
                'validateur_non_designe',
                403,
            );
        }
    }

    private function exigerUnAdministrateur(User $utilisateur): void
    {
        if (! $utilisateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }
    }

    /** @param array<string, mixed> $complements */
    private function passer(Logement $logement, EtatPublication $etat, User $auteur, string $recit, array $complements): void
    {
        $avant = $logement->etat_publication;
        $logement->forceFill(['etat_publication' => $etat, ...$complements])->saveQuietly();

        $this->journal->consigner('publication_'.$etat->value, $recit.' : '.$logement->libelleAudit().'.', $logement,
            ['etat_publication' => $avant->value], ['etat_publication' => $etat->value], $auteur);
    }
}
