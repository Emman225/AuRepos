<?php

namespace Database\Factories;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Apporteur> */
class ApporteurFactory extends Factory
{
    protected $model = Apporteur::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profil(Profil::Apporteur),
            'pourcentage' => 10,
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
