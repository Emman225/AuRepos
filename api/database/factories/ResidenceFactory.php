<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Quartier;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\Ville;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Residence> */
class ResidenceFactory extends Factory
{
    protected $model = Residence::class;

    public function definition(): array
    {
        return [
            'proprietaire_id' => Proprietaire::factory(),
            'quartier_id' => fn () => self::unQuartier()->id,
            'nom' => 'Résidence '.fake()->lastName().' '.fake()->numerify('###'),
            'adresse' => fake()->streetAddress(),
            'repere' => 'Derrière la pharmacie',
            'description' => fake()->paragraph(),
        ];
    }

    /** Un quartier prêt à l'emploi, créé une seule fois par base de test. */
    public static function unQuartier(string $commune = 'Cocody', string $quartier = 'Angré'): Quartier
    {
        $region = Region::firstOrCreate(['nom' => 'District autonome d’Abidjan']);
        $ville = Ville::firstOrCreate(['region_id' => $region->id, 'nom' => 'Abidjan']);
        $c = Commune::firstOrCreate(['ville_id' => $ville->id, 'nom' => $commune]);

        return Quartier::firstOrCreate(['commune_id' => $c->id, 'nom' => $quartier]);
    }
}
