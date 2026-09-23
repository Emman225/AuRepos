<?php

namespace App\Domain\Referentiels\Models;

/**
 * Type de véhicule (transferts seulement, CdC § 6.6) : berline, van, minibus…
 * Sert de base au barème (zone × type de véhicule) et au choix du client lors de sa demande.
 *
 * @property string $nom
 * @property int|null $capacite
 */
class TypeVehicule extends ElementReferentiel
{
    protected $table = 'types_vehicule';

    protected $fillable = ['nom', 'capacite', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'capacite' => 'integer'];
    }

    protected function nature(): string
    {
        return 'type de véhicule';
    }
}
