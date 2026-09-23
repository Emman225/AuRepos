<?php

namespace App\Domain\Catalogue\Enums;

/** Réglage par résidence (CdC § 7.1). */
enum ModeDeVente: string
{
    /** Le client choisit l'unité. */
    case Logement = 'logement';
    /** Le client choisit un type ; l'entreprise affecte l'unité à la confirmation. */
    case Type = 'type';
}
