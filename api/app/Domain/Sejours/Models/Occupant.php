<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Partenaires\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Occupant d'un séjour (fiche de police). Le numéro de pièce est chiffré en base ; sa photo,
 * si l'agent en dépose une au check-in (P2-SEJ-01), réutilise le mécanisme chiffré des pièces
 * justificatives (polymorphe), comme le dossier d'un propriétaire.
 *
 * @property int $id
 * @property int $sejour_id
 * @property string $nom
 * @property string|null $prenoms
 * @property bool $enfant
 * @property string|null $type_piece
 * @property string|null $numero_piece
 */
class Occupant extends Model
{
    protected $table = 'occupants';

    protected $fillable = ['sejour_id', 'nom', 'prenoms', 'enfant', 'type_piece', 'numero_piece', 'telephone'];

    protected $hidden = ['numero_piece'];

    protected function casts(): array
    {
        return ['enfant' => 'boolean', 'numero_piece' => 'encrypted'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return MorphMany<PieceJustificative, $this> */
    public function pieces(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'titulaire');
    }
}
