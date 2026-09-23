<?php

namespace App\Domain\Referentiels\Models;

class Equipement extends ElementReferentiel
{
    protected $table = 'equipements';

    protected $fillable = ['nom', 'portee', 'icone', 'filtre_recherche', 'ordre', 'actif'];

    protected function nature(): string
    {
        return 'équipement';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean', 'filtre_recherche' => 'boolean'];
    }
}
