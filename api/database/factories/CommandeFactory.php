<?php

namespace Database\Factories;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Commande> */
class CommandeFactory extends Factory
{
    protected $model = Commande::class;

    public function definition(): array
    {
        return [
            'sejour_id' => Sejour::factory(),
            'restaurateur_id' => Restaurateur::factory()->avecPourcentage(),
            'etat' => EtatDeCommande::Demande,
            'mode_reglement' => 'note_du_sejour',
            'montant_total' => 5000,
        ];
    }
}
