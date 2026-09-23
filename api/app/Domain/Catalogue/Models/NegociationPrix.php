<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de l'historique de négociation du prix propriétaire.
 *
 * @property int $id
 * @property int $logement_id
 * @property int $auteur_id
 * @property string $partie
 * @property int $montant
 * @property string|null $commentaire
 * @property string $nature
 * @property-read User $auteur
 */
class NegociationPrix extends Model
{
    use EstAudite;

    protected $table = 'negociations_prix';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function libelleAudit(): string
    {
        return ($this->nature === 'accord' ? 'accord' : 'proposition').' de prix propriétaire à '
            .number_format($this->montant, 0, ',', ' ').' F sur le logement n° '.$this->logement_id;
    }
}
