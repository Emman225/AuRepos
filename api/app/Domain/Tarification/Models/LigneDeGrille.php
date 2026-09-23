<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un croisement de la grille : (type OU logement) × saison × tranche de durée → tarif par nuit.
 *
 * @property int $id
 * @property int|null $type_logement_id
 * @property int|null $logement_id
 * @property int $saison_id
 * @property int $tranche_duree_id
 * @property int $tarif
 * @property-read Saison $saison
 * @property-read TrancheDuree $tranche
 */
class LigneDeGrille extends Model
{
    use EstAudite;

    protected $table = 'grille_tarifaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tarif' => 'integer'];
    }

    /** @return BelongsTo<Saison, $this> */
    public function saison(): BelongsTo
    {
        return $this->belongsTo(Saison::class);
    }

    /** @return BelongsTo<TrancheDuree, $this> */
    public function tranche(): BelongsTo
    {
        return $this->belongsTo(TrancheDuree::class, 'tranche_duree_id');
    }

    public function libelleAudit(): string
    {
        $cible = $this->logement_id ? 'logement n° '.$this->logement_id : 'type n° '.$this->type_logement_id;

        return "tarif de grille ({$cible}, saison n° {$this->saison_id}, tranche n° {$this->tranche_duree_id})";
    }
}
