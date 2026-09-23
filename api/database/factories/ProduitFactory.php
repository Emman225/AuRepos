<?php

namespace Database\Factories;

use App\Domain\Repas\Enums\CategorieProduit;
use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Produit> */
class ProduitFactory extends Factory
{
    protected $model = Produit::class;

    public function definition(): array
    {
        return [
            'restaurateur_id' => Restaurateur::factory()->avecPourcentage(),
            'nom' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'categorie' => CategorieProduit::Plat,
            'prix_restaurateur' => fake()->numberBetween(1000, 5000),
            'disponible' => true,
        ];
    }

    public function boisson(): static
    {
        return $this->state(fn () => ['categorie' => CategorieProduit::Boisson]);
    }

    public function indisponible(): static
    {
        return $this->state(fn () => ['disponible' => false]);
    }
}
