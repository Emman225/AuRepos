<?php

namespace App\Domain\Referentiels\Models;

class StatutMetier extends ElementReferentiel
{
    protected $table = 'statuts_metier';

    protected $fillable = ['domaine', 'code', 'libelle', 'couleur', 'ordre', 'actif'];

    protected function nature(): string
    {
        return 'statut métier';
    }
}
