<?php

namespace App\Domain\Caisse\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Retenue sur caution (P2-CAU-01/02, CdC § 6.3) : jamais un mouvement de caisse — rien n'est
 * décaissé sur la part retenue, elle reste acquise à l'entreprise — mais toujours un motif
 * obligatoire, des justificatifs (photos, même mécanisme chiffré que les pièces d'identité et
 * les états des lieux, via `titulaire`) et une facture normalisée « frais de dégradation /
 * retard » (`Facture`, type `frais_caution`) engendrée dans la même transaction.
 *
 * @property int $id
 * @property int $sejour_id
 * @property int $montant
 * @property string $motif
 * @property int $facture_id
 * @property int $effectuee_par
 * @property Carbon $effectuee_le
 * @property-read Sejour $sejour
 * @property-read Facture $facture
 */
class RetenueDeCaution extends Model
{
    use EstAudite;

    protected $table = 'retenues_caution';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'effectuee_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Facture, $this> */
    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'effectuee_par');
    }

    /** Photos et justificatifs du dégât ou du retard (P2-CAU-01), chiffrés — même mécanisme que partout ailleurs. @return MorphMany<PieceJustificative, $this> */
    public function pieces(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'titulaire');
    }

    public function libelleAudit(): string
    {
        return 'retenue de '.number_format($this->montant, 0, ',', ' ').' F sur la caution du '.$this->sejour->libelleAudit();
    }
}
