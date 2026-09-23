<?php

namespace Database\Factories;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Livreur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Livreur> */
class LivreurFactory extends Factory
{
    protected $model = Livreur::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profil(Profil::Livreur),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
