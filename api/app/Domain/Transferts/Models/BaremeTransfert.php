<?php

namespace App\Domain\Transferts\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\TypeVehicule;
use Database\Factories\BaremeTransfertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barème des transferts (CdC § 6.6) : « Le prix vient du barème zone × type de véhicule. »
 * La zone réutilise le découpage géographique existant (communes) : aucun nouveau référentiel
 * géographique. Un seul prix par couple commune × type de véhicule.
 *
 * @property int $id
 * @property int $commune_id
 * @property int $type_vehicule_id
 * @property int $prix
 * @property-read Commune $commune
 * @property-read TypeVehicule $typeVehicule
 */
class BaremeTransfert extends Model
{
    /** @use HasFactory<BaremeTransfertFactory> */
    use EstAudite, HasFactory;

    protected $table = 'baremes_transfert';

    protected $fillable = ['commune_id', 'type_vehicule_id', 'prix'];

    protected function casts(): array
    {
        return ['prix' => 'integer'];
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** @return BelongsTo<TypeVehicule, $this> */
    public function typeVehicule(): BelongsTo
    {
        return $this->belongsTo(TypeVehicule::class, 'type_vehicule_id');
    }

    public function libelleAudit(): string
    {
        return 'barème de transfert « '.($this->relationLoaded('commune') ? $this->commune->nom : $this->commune_id)
            .' × '.($this->relationLoaded('typeVehicule') ? $this->typeVehicule->nom : $this->type_vehicule_id).' »';
    }

    protected static function newFactory(): BaremeTransfertFactory
    {
        return BaremeTransfertFactory::new();
    }
}
