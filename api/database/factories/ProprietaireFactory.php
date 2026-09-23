<?php

namespace Database\Factories;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\NatureJuridique;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Proprietaire> */
class ProprietaireFactory extends Factory
{
    protected $model = Proprietaire::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->profil(Profil::Proprietaire),
            'raison_sociale' => null,
            'interne' => false,
        ];
    }

    public function entreprise(RegimeFiscal $regime = RegimeFiscal::MicroEntreprise): static
    {
        return $this->state(fn () => [
            'nature' => NatureJuridique::Entreprise, 'raison_sociale' => 'SCI '.fake()->lastName(), 'regime_fiscal' => $regime,
        ]);
    }

    public function interne(): static
    {
        return $this->state(fn () => ['interne' => true, 'raison_sociale' => 'DALAKOUN']);
    }
}
