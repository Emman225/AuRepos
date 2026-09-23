<?php

namespace App\Domain\Referentiels\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends ElementReferentiel
{
    protected $table = 'regions';

    protected $fillable = ['nom', 'actif'];

    protected function nature(): string
    {
        return 'région';
    }

    /** @return HasMany<Ville, $this> */
    public function villes(): HasMany
    {
        return $this->hasMany(Ville::class);
    }
}
