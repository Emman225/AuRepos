<?php

namespace App\Domain\Fidelite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $client_id
 * @property string $nature
 * @property int $points
 * @property string $libelle
 * @property int $montant_par_point
 * @property int $valeur_du_point
 */
class MouvementPoints extends Model
{
    protected $table = 'mouvements_points';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['points' => 'integer', 'montant_par_point' => 'integer', 'valeur_du_point' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function origine(): MorphTo
    {
        return $this->morphTo();
    }

    /** Valeur en francs de ce mouvement, au barème qui était en vigueur. */
    public function valeurEnFrancs(): int
    {
        return abs($this->points) * $this->valeur_du_point;
    }
}
