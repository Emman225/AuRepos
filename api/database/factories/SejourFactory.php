<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée la LIGNE du séjour seulement. Pour qu'il occupe le calendrier, passer par
 * App\Domain\Sejours\Services\Calendrier::occuper().
 *
 * @extends Factory<Sejour>
 */
class SejourFactory extends Factory
{
    protected $model = Sejour::class;

    public function definition(): array
    {
        return [
            'logement_id' => Logement::factory(),
            'arrivee' => '2026-11-10',
            'depart' => '2026-11-13',
            'adultes' => 2,
        ];
    }
}
