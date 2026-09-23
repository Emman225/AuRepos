<?php

namespace Database\Seeders;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Models\Extra;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration LOCAL : un compte par profil, mot de passe « password ».
 * Ne jamais lancer en recette ni en production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Le jeu de démonstration ne se lance pas en production.');

            return;
        }

        $this->call(ReferentielsSeeder::class);

        $agence = Agence::firstOrCreate(['nom' => 'Agence Cocody'], ['adresse' => 'Cocody, Abidjan']);

        foreach (Profil::cases() as $profil) {
            User::factory()->profil($profil)->create([
                'nom' => $profil->libelle(),
                'prenoms' => 'Démo',
                'email' => str_replace('_', '.', $profil->value).'@residences.test',
                // Les trois administrateurs du circuit de preuve doivent pouvoir encaisser.
                'agence_id' => $profil->estAdministrateur() ? $agence->id : null,
            ]);
        }

        // Le circuit de preuve exige trois administrateurs distincts (CdC § 3).
        foreach ([2, 3] as $n) {
            User::factory()->profil(Profil::Administrateur)->create([
                'nom' => "Administrateur {$n}",
                'prenoms' => 'Démo',
                'email' => "administrateur{$n}@residences.test",
                'agence_id' => $agence->id,
            ]);
        }

        // Catalogue des extras (P2-EXT-01) : le CdC ne fixe aucune liste — deux exemples
        // génériques pour que la démo montre quelque chose, jamais un catalogue inventé.
        Extra::firstOrCreate(['nom' => 'Late check-out'], ['description' => 'Départ retardé jusqu’à 15h.', 'prix' => 10000]);
        Extra::firstOrCreate(['nom' => 'Lit bébé'], ['description' => 'Lit bébé installé dans le logement.', 'prix' => 5000]);

        $this->call(DemoContenuSeeder::class);
    }
}
