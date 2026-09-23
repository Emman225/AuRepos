<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un devis de séjour (CdC § 5.1) : prix figés par le serveur, transformable en réservation
 * d'un clic, jamais effacé — seulement archivé.
 *
 * @property int $id
 * @property string $reference
 * @property int $logement_id
 * @property int $client_id
 * @property Carbon $arrivee
 * @property Carbon $depart
 * @property int $adultes
 * @property int $enfants
 * @property bool $arrivee_tardive
 * @property bool $depart_tardif
 * @property string|null $heure_arrivee_prevue
 * @property string|null $code_promo
 * @property array<string, mixed> $devis
 * @property int $net_a_payer
 * @property int $caution
 * @property int $points_utilises
 * @property int $reduction_points
 * @property string $etat
 * @property int|null $sejour_id
 * @property int|null $cree_par
 * @property-read Logement $logement
 * @property-read User $client
 * @property-read Sejour|null $sejour
 */
class Devis extends Model
{
    use EstAudite;

    protected $table = 'devis';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente', 'adultes' => 1, 'enfants' => 0, 'points_utilises' => 0, 'reduction_points' => 0];

    protected function casts(): array
    {
        return [
            'arrivee' => 'date', 'depart' => 'date', 'arrivee_tardive' => 'boolean', 'depart_tardif' => 'boolean',
            'devis' => 'array', 'net_a_payer' => 'integer', 'caution' => 'integer',
            'points_utilises' => 'integer', 'reduction_points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Devis $d) => $d->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (Devis $d): void {
            $d->reference = 'DEV-'.str_pad((string) $d->id, 6, '0', STR_PAD_LEFT);
            $d->saveQuietly();
        });
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    public function enAttente(): bool
    {
        return $this->etat === 'en_attente';
    }

    public function libelleAudit(): string
    {
        return 'devis '.$this->reference;
    }
}
