<?php

namespace App\Domain\Contenu\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Abonné à la lettre d'information (CdC § 12, écran Paramètres › Divers, P1-BO-10).
 * Inscription publique en libre-service ; désabonnement manuel côté back office pour l'instant
 * (aucune passerelle d'envoi de masse réelle dans ce lot — voir le journal de décisions).
 *
 * @property int $id
 * @property string $email
 * @property string|null $nom
 * @property bool $actif
 * @property Carbon $abonne_le
 * @property Carbon|null $desabonne_le
 */
class AbonneNewsletter extends Model
{
    use EstAudite;

    protected $table = 'abonnes_newsletter';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'abonne_le' => 'datetime', 'desabonne_le' => 'datetime'];
    }

    public function libelleAudit(): string
    {
        return 'abonné newsletter « '.$this->email.' »';
    }
}
