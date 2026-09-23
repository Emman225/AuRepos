<?php

namespace App\Domain\Referentiels\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ville extends ElementReferentiel
{
    protected $table = 'villes';

    protected $fillable = ['region_id', 'nom', 'actif'];

    protected function nature(): string
    {
        return 'ville';
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return HasMany<Commune, $this> */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
