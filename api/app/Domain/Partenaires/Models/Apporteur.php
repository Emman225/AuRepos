<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Database\Factories\ApporteurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Fiche de l'apporteur d'affaires : parraine des clients par un code unique, gagne une
 * commission sur chaque tranche encaissée d'un séjour d'un filleul (jamais sur le total,
 * jamais sur la caution).
 *
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property string $pourcentage
 * @property bool $actif
 * @property-read User $utilisateur
 */
class Apporteur extends Model
{
    /** @use HasFactory<ApporteurFactory> */
    use EstAudite, HasFactory;

    protected $table = 'apporteurs';

    protected $fillable = ['user_id', 'code', 'pourcentage', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return [
            'pourcentage' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Apporteur $apporteur): void {
            if (blank($apporteur->code)) {
                $apporteur->code = self::genererUnCode();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Les clients qu'il a parrainés. @return HasMany<User, $this> */
    public function filleuls(): HasMany
    {
        return $this->hasMany(User::class, 'parraine_par_id');
    }

    /** @return HasMany<CommissionApporteur, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(CommissionApporteur::class);
    }

    public function nomAffiche(): string
    {
        return $this->utilisateur->nomComplet();
    }

    public function libelleAudit(): string
    {
        return 'apporteur « '.$this->nomAffiche().' »';
    }

    /** Boucle de vérification d'unicité, comme App\Domain\Comptes\Services\ComptesDuPersonnel::genererUnIdentifiant. */
    private static function genererUnCode(): string
    {
        do {
            $code = 'APP-'.Str::upper(Str::random(6));
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    protected static function newFactory(): ApporteurFactory
    {
        return ApporteurFactory::new();
    }
}
