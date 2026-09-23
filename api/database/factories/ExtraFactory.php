<?php

namespace Database\Factories;

use App\Domain\Extras\Models\Extra;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Extra> */
class ExtraFactory extends Factory
{
    protected $model = Extra::class;

    public function definition(): array
    {
        return [
            'nom' => 'Late check-out',
            'description' => 'Départ retardé jusqu’à 15h.',
            'prix' => 10000,
            'actif' => true,
        ];
    }
}
