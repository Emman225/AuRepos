<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dates retirées de la vente : maintenance, usage du propriétaire, saison fermée (CdC § 6.2).
 *
 * @property int $id
 * @property int $logement_id
 * @property Carbon $debut
 * @property Carbon $fin
 * @property string $motif
 * @property string|null $commentaire
 */
class BlocageCalendrier extends Model
{
    use EstAudite;

    public const MOTIFS = [
        'maintenance' => 'Maintenance', 'usage_proprietaire' => 'Usage du propriétaire',
        'saison_fermee' => 'Saison fermée', 'canal_externe' => 'Réservation d’un canal externe',
    ];

    protected $table = 'blocages_calendrier';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['debut' => 'date', 'fin' => 'date'];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    public function libelleAudit(): string
    {
        return 'blocage « '.(self::MOTIFS[$this->motif] ?? $this->motif).' » du '.$this->debut->format('d/m/Y').' au '.$this->fin->format('d/m/Y');
    }
}
