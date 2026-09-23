<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Residence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Code promo (CdC § 7.3) : réduction en pourcentage ou en montant, sur une période de
 * validité, activable pour toutes les résidences ou une seule.
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property int $valeur
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 * @property int|null $residence_id
 * @property bool $actif
 * @property string|null $description
 * @property int $cree_par
 */
class CodePromo extends Model
{
    use EstAudite;

    protected $table = 'codes_promo';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['valeur' => 'integer', 'date_debut' => 'date', 'date_fin' => 'date', 'actif' => 'boolean'];
    }

    /** @return BelongsTo<Residence, $this> */
    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function valableLe(Carbon $date): bool
    {
        return $this->actif && $date->betweenIncluded($this->date_debut->copy()->startOfDay(), $this->date_fin->copy()->endOfDay());
    }

    public function valablePour(Residence $residence): bool
    {
        return $this->residence_id === null || $this->residence_id === $residence->id;
    }

    public function libelleAudit(): string
    {
        return 'code promo « '.$this->code.' »';
    }
}
