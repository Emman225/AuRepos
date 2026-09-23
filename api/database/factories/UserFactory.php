<?php

namespace Database\Factories;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => null,
            'identifiant' => null,
            'password' => static::$password ??= Hash::make('password'),
            'profil' => Profil::Client,
            'statut' => StatutCompte::Actif,
            'email_verified_at' => now(),
        ];
    }

    public function profil(Profil $profil): static
    {
        return $this->state(fn () => ['profil' => $profil]);
    }

    public function bloque(): static
    {
        return $this->state(fn () => ['statut' => StatutCompte::Bloque]);
    }

    public function enAttente(): static
    {
        return $this->state(fn () => ['statut' => StatutCompte::EnAttente, 'email_verified_at' => null]);
    }
}
