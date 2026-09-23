<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Models\ArticleInventaire;
use App\Support\Api\ErreurMetier;

/** Inventaire par logement (P2-MNT-02) : nom, quantité, valeur de remplacement. */
final class Inventaire
{
    public function ajouter(Logement $logement, string $nom, int $quantite, ?int $valeurRemplacement, User $auteur): ArticleInventaire
    {
        $this->validerLaQuantite($quantite);

        return ArticleInventaire::create([
            'logement_id' => $logement->id, 'nom' => trim($nom), 'quantite' => $quantite,
            'valeur_remplacement' => $valeurRemplacement, 'cree_par' => $auteur->id,
        ])->refresh();
    }

    /** @param array{nom?: string, quantite?: int, valeur_remplacement?: int|null} $donnees */
    public function modifier(ArticleInventaire $article, array $donnees): ArticleInventaire
    {
        if (array_key_exists('quantite', $donnees)) {
            $this->validerLaQuantite((int) $donnees['quantite']);
        }
        if (array_key_exists('nom', $donnees)) {
            $donnees['nom'] = trim((string) $donnees['nom']);
        }

        $article->update($donnees);

        return $article->refresh();
    }

    public function supprimer(ArticleInventaire $article): void
    {
        $article->delete();
    }

    private function validerLaQuantite(int $quantite): void
    {
        if ($quantite < 0) {
            throw new ErreurMetier('La quantité doit être positive.', 'quantite_invalide', 422);
        }
    }
}
