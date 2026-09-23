<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Referentiels\Models\TypeLogement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Logement> */
class LogementFactory extends Factory
{
    protected $model = Logement::class;

    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'type_logement_id' => fn () => TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3])->id,
            'nom' => 'Appartement '.fake()->bothify('?###'),
            'nombre_pieces' => 3,
            'nombre_chambres' => 2,
            'nombre_lits' => 2,
            'nombre_salles_de_bain' => 1,
            'capacite_de_base' => 2,
            'capacite_maximale' => 4,
            'surface_m2' => 75,
            'caution' => 50000,
        ];
    }
}
