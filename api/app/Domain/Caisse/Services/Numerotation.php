<?php

namespace App\Domain\Caisse\Services;

use Illuminate\Support\Facades\DB;

/**
 * Numérotation SANS TROU. À appeler DANS la transaction qui crée le document : la ligne du compteur
 * reste verrouillée jusqu'au commit, donc deux finalisations simultanées prennent deux numéros qui se
 * suivent, et une transaction annulée rend son numéro.
 */
final class Numerotation
{
    public function suivant(string $cle, int $annee): int
    {
        $ligne = DB::selectOne(
            'INSERT INTO compteurs (cle, annee, valeur) VALUES (?, ?, 1)
             ON CONFLICT (cle, annee) DO UPDATE SET valeur = compteurs.valeur + 1
             RETURNING valeur',
            [$cle, $annee],
        );

        return (int) $ligne->valeur;
    }
}
