<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\StatutAvis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avis vérifié de fin de séjour (CdC § 5.1, P2-AVI-01) : un client note un séjour CLÔTURÉ,
 * jamais avant, jamais plus d'une fois. Modéré par un administrateur avant publication.
 *
 * @property int $id
 * @property int $sejour_id
 * @property int $note
 * @property string|null $commentaire
 * @property StatutAvis $statut
 * @property int|null $modere_par
 * @property Carbon|null $modere_le
 * @property string|null $motif_refus
 * @property-read Sejour $sejour
 */
class Avis extends Model
{
    use EstAudite;

    protected $table = 'avis';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'en_attente'];

    protected function casts(): array
    {
        return ['note' => 'integer', 'statut' => StatutAvis::class, 'modere_le' => 'datetime'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function modere(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modere_par');
    }

    /** @param  Builder<Avis>  $requete */
    public function scopePublies(Builder $requete): void
    {
        $requete->where('statut', StatutAvis::Publie);
    }

    public function libelleAudit(): string
    {
        return 'avis sur le séjour '.$this->sejour->reference;
    }
}
