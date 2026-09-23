<?php

namespace App\Domain\Caisse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Part d'un règlement affectée à une affaire (un séjour aujourd'hui ; extras, transferts, repas demain).
 *
 * @property int $id
 * @property int $reglement_id
 * @property string $affaire_type
 * @property int $affaire_id
 * @property int $montant
 * @property-read Reglement $reglement
 */
class Imputation extends Model
{
    protected $table = 'imputations_reglement';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    /** @return MorphTo<Model, $this> */
    public function affaire(): MorphTo
    {
        return $this->morphTo();
    }
}
