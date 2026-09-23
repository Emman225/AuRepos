<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Catalogue\Models\Residence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une fermeture « Occupée » d'une résidence, du clic qui la ferme au clic (ou à la date prévue)
 * qui la rouvre (CdC § 6.2 : « l'historique des fermetures est journalisé et compte dans le
 * taux de disponibilité du propriétaire »). Posée par App\Http\Controllers\Api\V1\Backoffice\CalendrierController::disponibilite.
 *
 * @property int $id
 * @property int $residence_id
 * @property Carbon $fermee_le
 * @property Carbon|null $rouverte_le
 * @property Carbon|null $reouverture_prevue_le
 * @property string|null $motif
 * @property int|null $fermee_par
 * @property int|null $rouverte_par
 * @property-read Residence $residence
 */
class FermetureResidence extends Model
{
    protected $table = 'fermetures_residence';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fermee_le' => 'datetime', 'rouverte_le' => 'datetime', 'reouverture_prevue_le' => 'date'];
    }

    /** @return BelongsTo<Residence, $this> */
    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function enCours(): bool
    {
        return $this->rouverte_le === null;
    }

    /** Durée fermée, en jours, bornée à aujourd'hui si la fermeture est toujours en cours. */
    public function dureeEnJours(): int
    {
        return (int) $this->fermee_le->diffInDays($this->rouverte_le ?? Carbon::now());
    }
}
