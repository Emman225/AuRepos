<?php

namespace App\Domain\Contenu\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;

/**
 * Diapositive du carrousel de la page d'accueil (CdC § 12, écran Paramètres › Divers, P1-BO-10).
 * Distincte des bannières promotionnelles (`Banniere`, CdC § 5.1) : le carrousel est le visuel
 * d'en-tête de la page d'accueil (au-dessus de la recherche), la bannière une mise en avant
 * commerciale plus bas sur la page — deux sections séparées du CdC, malgré leur forme voisine.
 *
 * @property int $id
 * @property string $image_url
 * @property string|null $legende
 * @property string|null $lien
 * @property int $ordre
 * @property bool $actif
 * @property int $cree_par
 */
class Diapositive extends Model
{
    use EstAudite;

    protected $table = 'carrousel';

    protected $guarded = [];

    protected $attributes = ['ordre' => 0, 'actif' => true];

    protected function casts(): array
    {
        return ['ordre' => 'integer', 'actif' => 'boolean'];
    }

    public function libelleAudit(): string
    {
        return 'diapositive du carrousel #'.$this->id;
    }
}
