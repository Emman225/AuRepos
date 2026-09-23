<?php

namespace App\Domain\Notifications\Services;

/**
 * Remplace les `{{ jetons }}` d'un modèle de message par les valeurs fournies.
 * Volontairement simple : ce sont des paramètres texte courts, pas des vues.
 */
final class RenduDeModele
{
    /** @param  array<string, string|int|null>  $donnees */
    public static function rendre(string $gabarit, array $donnees): string
    {
        $recherche = [];
        $remplacement = [];
        foreach ($donnees as $cle => $valeur) {
            $recherche[] = '{{ '.$cle.' }}';
            $recherche[] = '{{'.$cle.'}}';
            $remplacement[] = (string) $valeur;
            $remplacement[] = (string) $valeur;
        }

        return str_replace($recherche, $remplacement, $gabarit);
    }
}
