<?php

namespace App\Domain\Caisse\Models;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un règlement, encaissement ou décaissement, et son circuit de preuve.
 * Il ne change d'état que par App\Domain\Caisse\Services\Caisse.
 *
 * @property int $id
 * @property string $reference
 * @property string $sens
 * @property Guichet $guichet
 * @property int $agence_id
 * @property int $tiers_id
 * @property int $montant
 * @property ModeDeReglement $mode
 * @property string|null $reference_du_mode
 * @property string $notes
 * @property EtatDuReglement $etat
 * @property int $saisi_par
 * @property Carbon $saisi_le
 * @property int|null $valide_par
 * @property Carbon|null $valide_le
 * @property int|null $preuve_par
 * @property Carbon|null $preuve_le
 * @property string|null $preuve_chemin
 * @property string|null $preuve_nom
 * @property string|null $preuve_mime
 * @property int|null $finalise_par
 * @property Carbon|null $finalise_le
 * @property int|null $rejete_par
 * @property string|null $motif_rejet
 * @property string|null $numero_recu
 * @property bool $surplus_en_avance
 * @property string|null $recu_chemin
 * @property Carbon|null $recu_envoye_le
 * @property-read User $tiers
 * @property-read Agence $agence
 */
class Reglement extends Model
{
    protected $table = 'reglements';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente', 'surplus_en_avance' => false];

    protected function casts(): array
    {
        return [
            'guichet' => Guichet::class, 'mode' => ModeDeReglement::class, 'etat' => EtatDuReglement::class, 'montant' => 'integer',
            'saisi_le' => 'datetime', 'valide_le' => 'datetime', 'preuve_le' => 'datetime', 'finalise_le' => 'datetime', 'rejete_le' => 'datetime',
            'surplus_en_avance' => 'boolean', 'recu_envoye_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Reglement $r) => $r->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (Reglement $r): void {
            $r->reference = 'REG-'.str_pad((string) $r->id, 6, '0', STR_PAD_LEFT);
            $r->saveQuietly();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function tiers(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tiers_id');
    }

    /** @return BelongsTo<Agence, $this> */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /** @return HasMany<Imputation, $this> */
    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    /** @return BelongsTo<User, $this> */
    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    /** @return BelongsTo<User, $this> */
    public function porteurDeLaPreuve(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preuve_par');
    }

    public function estUnEncaissement(): bool
    {
        return $this->sens === 'encaissement';
    }

    public function libelleAudit(): string
    {
        $nature = $this->estUnEncaissement() ? 'encaissement' : 'décaissement';
        $reference = str_starts_with((string) $this->reference, 'REG-') ? ' '.$this->reference : '';

        return $nature.$reference.' de '.number_format($this->montant, 0, ',', ' ').' F';
    }
}
