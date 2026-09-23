<?php

namespace App\Domain\Caisse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $client_id
 * @property int $reglement_id
 * @property int $montant
 * @property int $solde
 * @property-read Reglement $reglement
 */
class AvanceClient extends Model
{
    protected $table = 'avances_client';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'solde' => 'integer'];
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }
}
