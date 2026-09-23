<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\TypeEtatDesLieux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * État des lieux d'entrée ou de sortie d'un séjour (P2-SEJ-02, CdC § 6.3) : inventaire,
 * signature à l'écran. Au plus un de chaque type par séjour.
 *
 * @property int $id
 * @property int $sejour_id
 * @property TypeEtatDesLieux $type
 * @property string|null $commentaire_general
 * @property string|null $signature
 * @property Carbon|null $signe_le
 * @property int|null $etabli_par
 * @property-read Sejour $sejour
 * @property-read Collection<int, LigneEtatDesLieux> $lignes
 */
class EtatDesLieux extends Model
{
    use EstAudite;

    protected $table = 'etats_des_lieux';

    protected $fillable = ['sejour_id', 'type', 'commentaire_general', 'etabli_par', 'signature', 'signe_le'];

    protected $hidden = ['signature'];

    protected function casts(): array
    {
        return ['type' => TypeEtatDesLieux::class, 'signature' => 'encrypted', 'signe_le' => 'datetime'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function etablisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par');
    }

    /** @return HasMany<LigneEtatDesLieux, $this> */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneEtatDesLieux::class, 'etat_des_lieux_id')->orderBy('ordre');
    }

    public function estSigne(): bool
    {
        return $this->signe_le !== null;
    }

    public function libelleAudit(): string
    {
        return $this->type->libelle().' du '.$this->sejour->libelleAudit();
    }
}
