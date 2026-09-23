<?php

namespace Database\Factories;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Transferts\Models\Chauffeur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Chauffeur> */
class ChauffeurFactory extends Factory
{
    protected $model = Chauffeur::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profil(Profil::Chauffeur),
            'actif' => true,
        ];
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
