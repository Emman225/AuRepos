<?php

namespace App\Domain\Referentiels\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de tous les référentiels : tracés dans le journal d'audit, et
 * désactivables plutôt que supprimables une fois utilisés.
 *
 * @property int $id
 * @property string $nom
 * @property bool $actif
 *
 * @method static Builder<static> actifs()
 */
abstract class ElementReferentiel extends Model
{
    use EstAudite;

    /** Nom de la nature de l'élément, pour le récit d'audit : « commune », « équipement »… */
    abstract protected function nature(): string;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /**
     * @param  Builder<static>  $requete
     * @return Builder<static>
     */
    public function scopeActifs(Builder $requete): Builder
    {
        return $requete->where($this->getTable().'.actif', true);
    }

    public function libelleAudit(): string
    {
        return $this->nature().' « '.($this->getAttribute('nom') ?? $this->getAttribute('libelle')).' »';
    }
}
