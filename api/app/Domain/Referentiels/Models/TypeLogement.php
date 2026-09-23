<?php

namespace App\Domain\Referentiels\Models;

class TypeLogement extends ElementReferentiel
{
    protected $table = 'types_logement';

    protected $fillable = ['code', 'nom', 'nombre_pieces', 'ordre', 'actif'];

    protected function nature(): string
    {
        return 'type de logement';
    }
}
