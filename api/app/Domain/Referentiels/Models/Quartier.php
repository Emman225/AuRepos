<?php

namespace App\Domain\Referentiels\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quartier extends ElementReferentiel
{
    protected $table = 'quartiers';

    protected $fillable = ['commune_id', 'nom', 'actif'];

    protected function nature(): string
    {
        return 'quartier';
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
