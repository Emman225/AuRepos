<?php

namespace App\Domain\Parametres\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $cle
 * @property mixed $valeur
 * @property int|null $modifie_par
 */
class Parametre extends Model
{
    use EstAudite;

    protected $table = 'parametres';

    protected $fillable = ['cle', 'valeur', 'modifie_par'];

    protected function casts(): array
    {
        return ['valeur' => 'json'];
    }

    public function libelleAudit(): string
    {
        $libelle = app(Parametres::class)->definition($this->cle)['libelle'] ?? $this->cle;

        return 'paramètre « '.$libelle.' »';
    }
}
