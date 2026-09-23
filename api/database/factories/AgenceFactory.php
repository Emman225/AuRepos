<?php

namespace Database\Factories;

use App\Domain\Comptes\Models\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agence>
 */
class AgenceFactory extends Factory
{
    protected $model = Agence::class;

    public function definition(): array
    {
        return [
            'nom' => 'Agence '.fake()->unique()->city(),
            'adresse' => fake()->streetAddress(),
            'telephone' => fake()->numerify('07########'),
            'active' => true,
        ];
    }
}
