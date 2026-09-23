<?php

namespace App\Domain\PaiementEnLigne\Models;

use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $reference
 * @property int $sejour_id
 * @property int $client_id
 * @property int $montant
 * @property string $etat
 * @property string $passerelle
 * @property string|null $reference_passerelle
 * @property string|null $mode_constate
 * @property string|null $url_paiement
 * @property int|null $reglement_id
 * @property array<string, mixed>|null $dernier_echange
 * @property int $verifications
 * @property Carbon|null $verifie_le
 * @property string|null $motif_echec
 * @property-read Sejour $sejour
 */
class PaiementEnLigne extends Model
{
    protected $table = 'paiements_en_ligne';

    protected $primaryKey = 'reference';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'initie', 'verifications' => 0];

    protected function casts(): array
    {
        return ['montant' => 'integer', 'dernier_echange' => 'array', 'verifie_le' => 'datetime', 'verifications' => 'integer'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    public function estTermine(): bool
    {
        return in_array($this->etat, ['reussi', 'echoue', 'expire'], true);
    }

    public function libelleAudit(): string
    {
        return 'paiement en ligne '.$this->reference.' de '.number_format($this->montant, 0, ',', ' ').' F';
    }
}
