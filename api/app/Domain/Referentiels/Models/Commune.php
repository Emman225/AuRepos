<?php

namespace App\Domain\Referentiels\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends ElementReferentiel
{
    protected $table = 'communes';

    protected $fillable = ['ville_id', 'nom', 'actif'];

    protected function nature(): string
    {
        return 'commune';
    }

    /** @return BelongsTo<Ville, $this> */
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class);
    }

    /** @return HasMany<Quartier, $this> */
    public function quartiers(): HasMany
    {
        return $this->hasMany(Quartier::class);
    }
}
