<?php

namespace App\Domain\Repas\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Database\Factories\RestaurateurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche du restaurateur partenaire (CdC — « Repas et boissons »). Même patron que
 * App\Domain\Partenaires\Models\Apporteur et App\Domain\Catalogue\Models\Proprietaire :
 * une fiche liée à un compte de connexion.
 *
 * `pourcentage_plateforme` fixe le prix de vente de chaque produit de sa carte
 * (prix de vente = prix restaurateur × (1 + pourcentage / 100)) ; comme le pourcentage
 * entreprise des propriétaires, tout changement passe par la double validation
 * (App\Domain\Validation\Services\DoubleValidation) — jamais modifiable directement.
 *
 * `assujetti_tva` reprend EXACTEMENT le champ de Proprietaire : la TVA s'ajoute à sa
 * dette si, et seulement si, il est assujetti.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $actif
 * @property bool $assujetti_tva
 * @property string|null $pourcentage_plateforme
 * @property-read User $utilisateur
 */
class Restaurateur extends Model
{
    /** @use HasFactory<RestaurateurFactory> */
    use EstAudite, HasFactory;

    protected $table = 'restaurateurs';

    protected $fillable = ['user_id', 'actif', 'assujetti_tva'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true, 'assujetti_tva' => false];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'assujetti_tva' => 'boolean',
            'pourcentage_plateforme' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Produit, $this> */
    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class);
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
        return 'restaurateur « '.$this->nomAffiche().' »';
    }

    protected static function newFactory(): RestaurateurFactory
    {
        return RestaurateurFactory::new();
    }
}
