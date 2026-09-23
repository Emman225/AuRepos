<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Caisse\Models\Reglement;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une commission gagnée par un apporteur sur UNE tranche encaissée d'un séjour d'un
 * filleul — jamais sur le total du séjour, jamais sur la caution (CdC). Une ligne par
 * règlement encaissé : `reglement_id` est unique, garde-fou anti-doublon si l'événement
 * de fin de circuit est rejoué.
 *
 * @property int $id
 * @property int $apporteur_id
 * @property int $sejour_id
 * @property int $reglement_id
 * @property int $montant
 * @property-read Apporteur $apporteur
 * @property-read Sejour $sejour
 * @property-read Reglement $reglement
 */
class CommissionApporteur extends Model
{
    protected $table = 'commissions_apporteurs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /** @return BelongsTo<Apporteur, $this> */
    public function apporteur(): BelongsTo
    {
        return $this->belongsTo(Apporteur::class);
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }
}
