<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Proprietaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $numero
 * @property int $sejour_id
 * @property int $proprietaire_id
 * @property int $nuitees
 * @property int|null $prix_proprietaire_par_nuit
 * @property string $etat
 * @property bool $valide_automatiquement
 * @property-read Sejour $sejour
 * @property-read Proprietaire $proprietaire
 */
class BonDeMiseADisposition extends Model
{
    use EstAudite;

    protected $table = 'bons_mise_a_disposition';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente', 'valide_automatiquement' => false];

    protected function casts(): array
    {
        return ['valide_automatiquement' => 'boolean', 'valide_le' => 'datetime', 'prix_proprietaire_par_nuit' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (BonDeMiseADisposition $b) => $b->numero = 'TMP-'.Str::uuid()->toString());
        static::created(function (BonDeMiseADisposition $b): void {
            $b->numero = 'BMD-'.str_pad((string) $b->id, 6, '0', STR_PAD_LEFT);
            $b->saveQuietly();
        });
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Proprietaire, $this> */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    public function libelleAudit(): string
    {
        return 'bon de mise à disposition'.(str_starts_with((string) $this->numero, 'BMD-') ? ' '.$this->numero : '');
    }
}
