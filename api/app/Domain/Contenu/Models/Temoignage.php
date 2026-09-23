<?php

namespace App\Domain\Contenu\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;

/**
 * Témoignage mis en avant sur la page d'accueil (CdC § 5.1). Distinct des avis vérifiés
 * de fin de séjour (P2-AVI-01, table `avis`) : un témoignage est un contenu éditorial
 * choisi par l'entreprise, pas la moyenne des notes clients.
 * Sa gestion reste à construire dans l'écran Paramètres › Divers (P1-BO-10).
 *
 * @property int $id
 * @property string $nom_client
 * @property string $message
 * @property int|null $note
 * @property string|null $photo_url
 * @property bool $publie
 * @property int $ordre
 * @property int $cree_par
 */
class Temoignage extends Model
{
    use EstAudite;

    protected $table = 'temoignages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['note' => 'integer', 'publie' => 'boolean', 'ordre' => 'integer'];
    }

    public function libelleAudit(): string
    {
        return 'témoignage de « '.$this->nom_client.' »';
    }
}
