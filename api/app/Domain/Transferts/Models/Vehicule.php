<?php

namespace App\Domain\Transferts\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Referentiels\Models\TypeVehicule;
use Database\Factories\VehiculeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Véhicule d'un chauffeur (CdC § 6.6, Paramètres › Véhicules de l'espace chauffeur).
 *
 * @property int $id
 * @property int $chauffeur_id
 * @property int $type_vehicule_id
 * @property string $immatriculation
 * @property bool $actif
 * @property-read Chauffeur $chauffeur
 * @property-read TypeVehicule $type
 */
class Vehicule extends Model
{
    /** @use HasFactory<VehiculeFactory> */
    use EstAudite, HasFactory;

    protected $table = 'vehicules';

    protected $fillable = ['chauffeur_id', 'type_vehicule_id', 'immatriculation', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return BelongsTo<Chauffeur, $this> */
    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    /** @return BelongsTo<TypeVehicule, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeVehicule::class, 'type_vehicule_id');
    }

    public function libelleAudit(): string
    {
        return 'véhicule « '.$this->immatriculation.' »';
    }

    protected static function newFactory(): VehiculeFactory
    {
        return VehiculeFactory::new();
    }
}
