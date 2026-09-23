<?php

namespace Database\Factories;

use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Transferts\Models\BaremeTransfert;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BaremeTransfert> */
class BaremeTransfertFactory extends Factory
{
    protected $model = BaremeTransfert::class;

    public function definition(): array
    {
        return [
            'commune_id' => fn () => $this->communeParDefaut(),
            'type_vehicule_id' => fn () => TypeVehicule::firstOrCreate(['nom' => 'Berline'], ['capacite' => 4])->id,
            'prix' => 15000,
        ];
    }

    private function communeParDefaut(): int
    {
        $region = Region::firstOrCreate(['nom' => 'District autonome d’Abidjan']);
        $ville = Ville::firstOrCreate(['region_id' => $region->id, 'nom' => 'Abidjan']);

        return Commune::firstOrCreate(['ville_id' => $ville->id, 'nom' => 'Cocody'])->id;
    }
}
