<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Referentiels\Models\ElementReferentiel;

/**
 * @property int $nuits_min
 * @property int|null $nuits_max
 */
class TrancheDuree extends ElementReferentiel
{
    protected $table = 'tranches_duree';

    protected $fillable = ['nom', 'nuits_min', 'nuits_max', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function nature(): string
    {
        return 'tranche de durée';
    }

    public function couvre(int $nuits): bool
    {
        return $nuits >= $this->nuits_min && ($this->nuits_max === null || $nuits <= $this->nuits_max);
    }
}
