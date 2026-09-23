<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Referentiels\Models\ElementReferentiel;
use Illuminate\Support\Carbon;

/**
 * @property string $categorie
 * @property Carbon $date_debut
 * @property Carbon $date_fin
 */
class Saison extends ElementReferentiel
{
    protected $table = 'saisons';

    protected $fillable = ['nom', 'categorie', 'date_debut', 'date_fin', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean', 'date_debut' => 'date', 'date_fin' => 'date'];
    }

    protected function nature(): string
    {
        return 'saison';
    }

    public function estUnEvenement(): bool
    {
        return $this->categorie === 'evenement';
    }

    public function couvre(Carbon $jour): bool
    {
        return $jour->betweenIncluded($this->date_debut, $this->date_fin);
    }
}
