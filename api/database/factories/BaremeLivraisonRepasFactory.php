<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Repas\Models\BaremeLivraisonRepas;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BaremeLivraisonRepas> */
class BaremeLivraisonRepasFactory extends Factory
{
    protected $model = BaremeLivraisonRepas::class;

    public function definition(): array
    {
        return [
            'residence_id' => Residence::factory(),
            'forfait' => 1500,
        ];
    }
}
