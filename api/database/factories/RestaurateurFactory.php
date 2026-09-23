<?php

namespace Database\Factories;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Restaurateur> */
class RestaurateurFactory extends Factory
{
    protected $model = Restaurateur::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profil(Profil::Restaurateur),
            'actif' => true,
            'assujetti_tva' => false,
        ];
    }

    /** Un pourcentage déjà en vigueur, comme s'il avait franchi la double validation. */
    public function avecPourcentage(float $pourcentage = 30): static
    {
        return $this->state(fn () => ['pourcentage_plateforme' => $pourcentage]);
    }

    public function assujettiTva(): static
    {
        return $this->state(fn () => ['assujetti_tva' => true]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
