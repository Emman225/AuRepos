<?php

namespace Database\Factories;

use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Crée la LIGNE du transfert seulement (état « demande »). Pour l'affecter,
 * passer par App\Domain\Transferts\Services\GestionDesTransferts::affecter().
 *
 * @extends Factory<Transfert>
 */
class TransfertFactory extends Factory
{
    protected $model = Transfert::class;

    public function definition(): array
    {
        return [
            'sejour_id' => Sejour::factory(),
            'lieu_de_prise_en_charge' => 'Aéroport Félix-Houphouët-Boigny',
            'commune_id' => fn () => $this->communeParDefaut(),
            'type_vehicule_souhaite_id' => fn () => TypeVehicule::firstOrCreate(['nom' => 'Berline'], ['capacite' => 4])->id,
            'date_heure_prevue' => now()->addDays(2),
            'nombre_passagers' => 2,
            'nombre_bagages' => 1,
            'montant' => 15000,
        ];
    }

    private function communeParDefaut(): int
    {
        $region = Region::firstOrCreate(['nom' => 'District autonome d’Abidjan']);
        $ville = Ville::firstOrCreate(['region_id' => $region->id, 'nom' => 'Abidjan']);

        return Commune::firstOrCreate(['ville_id' => $ville->id, 'nom' => 'Cocody'])->id;
    }
}
