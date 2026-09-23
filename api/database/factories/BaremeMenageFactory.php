<?php

namespace Database\Factories;

use App\Domain\Exploitation\Models\BaremeMenage;
use App\Domain\Referentiels\Models\TypeLogement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BaremeMenage> */
class BaremeMenageFactory extends Factory
{
    protected $model = BaremeMenage::class;

    public function definition(): array
    {
        return [
            'type_logement_id' => fn () => TypeLogement::firstOrCreate(['code' => 'f3'], ['nom' => 'Appartement 3 pièces', 'nombre_pieces' => 3])->id,
            'forfait' => 3000,
            'plancher' => 2000,
        ];
    }
}
