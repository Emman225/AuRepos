<?php

namespace App\Domain\Notifications\Enums;

/**
 * Les quatre modèles de message du CdC § 6.7 : confirmation, consignes d'arrivée,
 * rappel J-1, demande d'avis J+1. Le texte de chacun se règle dans les Paramètres
 * (onglet « Modèles de messages »), jamais dans le code.
 */
enum ModeleDeMessage: string
{
    case Confirmation = 'confirmation';
    case ConsignesArrivee = 'consignes_arrivee';
    case RappelJ1 = 'rappel_j1';
    case DemandeAvisJ1 = 'demande_avis_j1';

    public function libelle(): string
    {
        return match ($this) {
            self::Confirmation => 'Confirmation de réservation',
            self::ConsignesArrivee => 'Consignes d’arrivée',
            self::RappelJ1 => 'Rappel la veille de l’arrivée',
            self::DemandeAvisJ1 => 'Demande d’avis le lendemain du départ',
        };
    }

    /** Clé de base dans `config/parametres.php` : {clé}_sujet et {clé}_corps. */
    public function cleParametre(): string
    {
        return 'messages.'.$this->value;
    }
}
