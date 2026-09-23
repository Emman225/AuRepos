<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\ModeDeVente;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\Quartier;
use Database\Factories\ResidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Le site : une adresse, un quartier, des équipements communs, et des logements.
 *
 * @property int $id
 * @property int $proprietaire_id
 * @property int $quartier_id
 * @property string $nom
 * @property string $slug
 * @property string|null $adresse
 * @property string|null $repere
 * @property string|null $description
 * @property string|null $consignes_acces
 * @property ModeDeVente $mode_vente
 * @property Disponibilite $disponibilite
 * @property Carbon|null $reouverture_prevue_le
 * @property bool $active
 * @property bool $mise_en_avant
 * @property-read Proprietaire $proprietaire
 * @property-read Quartier $quartier
 */
class Residence extends Model
{
    /** @use HasFactory<ResidenceFactory> */
    use EstAudite, HasFactory, SoftDeletes;

    protected $table = 'residences';

    protected $fillable = [
        'proprietaire_id', 'quartier_id', 'nom', 'adresse', 'repere', 'latitude', 'longitude',
        'description', 'consignes_acces', 'mode_vente', 'disponibilite', 'reouverture_prevue_le', 'active', 'mise_en_avant',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['mise_en_avant' => false];

    protected function casts(): array
    {
        return [
            'mode_vente' => ModeDeVente::class,
            'disponibilite' => Disponibilite::class,
            'reouverture_prevue_le' => 'date',
            'active' => 'boolean',
            'mise_en_avant' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Adresse lisible et stable pour le site public ; elle ne change pas si le nom change,
        // pour ne pas casser les liens déjà partagés ou référencés.
        static::creating(function (Residence $residence): void {
            $base = Str::slug($residence->nom) ?: 'residence';
            $slug = $base;
            for ($n = 2; static::withTrashed()->where('slug', $slug)->exists(); $n++) {
                $slug = "{$base}-{$n}";
            }
            $residence->slug = $slug;
        });
    }

    /** @return BelongsTo<Proprietaire, $this> */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    /** @return BelongsTo<Quartier, $this> */
    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class);
    }

    /** @return HasMany<Logement, $this> */
    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class);
    }

    /**
     * Équipements communs au site : piscine, parking, groupe électrogène, gardiennage…
     *
     * @return BelongsToMany<Equipement, $this>
     */
    public function equipements(): BelongsToMany
    {
        return $this->belongsToMany(Equipement::class, 'equipement_residence');
    }

    public function libelleAudit(): string
    {
        return 'résidence « '.$this->nom.' »';
    }

    protected static function newFactory(): ResidenceFactory
    {
        return ResidenceFactory::new();
    }
}
