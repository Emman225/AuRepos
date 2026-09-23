<?php

namespace App\Domain\Validation\Models;

use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Proposition de changement d'une valeur sensible, en attente d'un second administrateur.
 *
 * @property int $id
 * @property string $sujet_type
 * @property int $sujet_id
 * @property string $champ
 * @property mixed $valeur_actuelle
 * @property mixed $valeur_proposee
 * @property string|null $motif
 * @property string $statut
 * @property int $propose_par
 * @property int|null $decide_par
 * @property Carbon|null $decide_le
 * @property string|null $motif_decision
 * @property-read User $auteur
 * @property-read User|null $decideur
 * @property-read Model|null $sujet
 */
class ChangementAValider extends Model
{
    protected $table = 'changements_a_valider';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'en_attente'];

    protected function casts(): array
    {
        return ['valeur_actuelle' => 'json', 'valeur_proposee' => 'json', 'decide_le' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par');
    }

    /** @return BelongsTo<User, $this> */
    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }

    public function enAttente(): bool
    {
        return $this->statut === 'en_attente';
    }
}
