<?php

namespace App\Domain\Extras\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Database\Factories\ExtraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue des extras (P2-EXT-01) : le CdC n'énumère aucune liste fixe (« late check-out »,
 * « lit bébé »… sont des exemples, pas une nomenclature fermée) — entièrement modifiable par
 * le back office, comme la carte d'un restaurateur (App\Domain\Repas\Models\Produit).
 *
 * @property int $id
 * @property string $nom
 * @property string|null $description
 * @property int $prix
 * @property bool $actif
 */
class Extra extends Model
{
    /** @use HasFactory<ExtraFactory> */
    use EstAudite, HasFactory;

    protected $table = 'extras';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['prix' => 'integer', 'actif' => 'boolean'];
    }

    /** @return HasMany<CommandeExtra, $this> */
    public function commandes(): HasMany
    {
        return $this->hasMany(CommandeExtra::class);
    }

    public function libelleAudit(): string
    {
        return 'extra « '.$this->nom.' »';
    }

    protected static function newFactory(): ExtraFactory
    {
        return ExtraFactory::new();
    }
}
