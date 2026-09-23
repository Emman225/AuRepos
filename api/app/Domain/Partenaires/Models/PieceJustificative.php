<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Partenaires\Enums\StatutDePiece;
use App\Domain\Partenaires\Enums\TypeDePiece;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Document d'un dossier (propriétaire aujourd'hui ; restaurateur, chauffeur, client à terme demain).
 * Le fichier est chiffré sur le disque privé : il ne se lit que par l'API, connecté et autorisé.
 *
 * @property int $id
 * @property TypeDePiece $type
 * @property string $chemin
 * @property string $nom_original
 * @property string $mime
 * @property int $taille_octets
 * @property StatutDePiece $statut
 * @property string|null $motif_refus
 * @property Carbon|null $expire_le
 * @property Carbon|null $verifiee_le
 */
class PieceJustificative extends Model
{
    use EstAudite;

    protected $table = 'pieces_justificatives';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TypeDePiece::class,
            'statut' => StatutDePiece::class,
            'expire_le' => 'date',
            'verifiee_le' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function titulaire(): MorphTo
    {
        return $this->morphTo();
    }

    /** Validée ET non périmée : seule une telle pièce compte dans un dossier. */
    public function estValable(): bool
    {
        return $this->statut === StatutDePiece::Validee && ($this->expire_le === null || $this->expire_le->isFuture());
    }

    public function libelleAudit(): string
    {
        return 'pièce « '.$this->type->libelle().' » ('.$this->nom_original.')';
    }
}
