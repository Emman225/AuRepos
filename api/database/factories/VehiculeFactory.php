<?php

namespace Database\Factories;

use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Vehicule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicule> */
class VehiculeFactory extends Factory
{
    protected $model = Vehicule::class;

    public function definition(): array
    {
        return [
            'chauffeur_id' => Chauffeur::factory(),
            'type_vehicule_id' => fn () => TypeVehicule::firstOrCreate(['nom' => 'Berline'], ['capacite' => 4])->id,
            'immatriculation' => mb_strtoupper($this->faker->bothify('??-####-??')),
            'actif' => true,
        ];
    }
}
