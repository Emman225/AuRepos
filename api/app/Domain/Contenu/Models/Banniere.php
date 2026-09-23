<?php

namespace App\Domain\Contenu\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;

/**
 * Bannière promotionnelle de la page d'accueil (CdC § 5.1 « promotions »).
 * Sa gestion (création, modification) reste à construire dans l'écran
 * Paramètres › Divers (P1-BO-10) ; pour l'instant, saisie en base uniquement.
 *
 * @property int $id
 * @property string $titre
 * @property string|null $sous_titre
 * @property string $image_url
 * @property string|null $lien
 * @property int $ordre
 * @property bool $actif
 * @property int $cree_par
 */
class Banniere extends Model
{
    use EstAudite;

    protected $table = 'bannieres';

    protected $guarded = [];

    protected $attributes = ['ordre' => 0, 'actif' => true];

    protected function casts(): array
    {
        return ['ordre' => 'integer', 'actif' => 'boolean'];
    }

    public function libelleAudit(): string
    {
        return 'bannière « '.$this->titre.' »';
    }
}
