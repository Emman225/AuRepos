<?php

namespace App\Domain\Repas\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Database\Factories\LivreurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche du livreur de repas (CdC — « Repas et boissons », espace livreur). Même patron
 * que Restaurateur : une fiche liée à un compte de connexion. Pas de véhicule — rien dans
 * le CdC ne le demande pour la livraison de repas, contrairement au chauffeur des transferts.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $actif
 * @property-read User $utilisateur
 */
class Livreur extends Model
{
    /** @use HasFactory<LivreurFactory> */
    use EstAudite, HasFactory;

    protected $table = 'livreurs';

    protected $fillable = ['user_id', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Commande, $this> */
    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    public function nomAffiche(): string
    {
        return $this->utilisateur->nomComplet();
    }

    public function libelleAudit(): string
    {
        return 'livreur « '.$this->nomAffiche().' »';
    }

    protected static function newFactory(): LivreurFactory
    {
        return LivreurFactory::new();
    }
}
