<?php

namespace Database\Factories;

use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée la LIGNE de la commande seulement (état « demande »). Pour la faire avancer, passer par
 * App\Domain\Extras\Services\GestionDesExtras.
 *
 * @extends Factory<CommandeExtra>
 */
class CommandeExtraFactory extends Factory
{
    protected $model = CommandeExtra::class;

    public function definition(): array
    {
        return [
            'sejour_id' => Sejour::factory(),
            'extra_id' => Extra::factory(),
            'quantite' => 1,
            'nom_extra' => 'Late check-out',
            'prix_unitaire' => 10000,
            'montant_total' => 10000,
            'etat' => EtatDeCommandeExtra::Demande,
        ];
    }
}
