<?php

namespace App\Domain\Repas\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Repas\Enums\CategorieProduit;
use App\Support\Api\ErreurMetier;
use Database\Factories\ProduitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un plat ou une boisson de la carte d'un restaurateur (CdC — « Plats et boissons par
 * restaurateur : photo, description, prix d'achat (prix restaurateur), catégorie,
 * disponibilité »).
 *
 * Pas de photo ni de gestion de stock ici : le CdC ne donne aucune règle de stock
 * (décrément automatique, seuil…), `disponible` est un simple bouton du restaurateur.
 *
 * @property int $id
 * @property int $restaurateur_id
 * @property string $nom
 * @property string|null $description
 * @property CategorieProduit $categorie
 * @property int $prix_restaurateur
 * @property bool $disponible
 * @property-read Restaurateur $restaurateur
 */
class Produit extends Model
{
    /** @use HasFactory<ProduitFactory> */
    use EstAudite, HasFactory;

    protected $table = 'produits';

    protected $fillable = ['restaurateur_id', 'nom', 'description', 'categorie', 'prix_restaurateur', 'disponible'];

    /** @var array<string, mixed> */
    protected $attributes = ['categorie' => 'plat', 'disponible' => true];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieProduit::class,
            'prix_restaurateur' => 'integer',
            'disponible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Restaurateur, $this> */
    public function restaurateur(): BelongsTo
    {
        return $this->belongsTo(Restaurateur::class);
    }

    /** Prix de vente = prix restaurateur × (1 + pourcentage plateforme / 100), arrondi. */
    public function prixDeVente(): int
    {
        $pourcentage = $this->restaurateur->pourcentage_plateforme;

        if ($pourcentage === null) {
            throw new ErreurMetier(
                'Ce restaurateur n’a pas encore de pourcentage plateforme validé : impossible de calculer un prix de vente.',
                'pourcentage_plateforme_absent',
                422,
            );
        }

        return (int) round($this->prix_restaurateur * (1 + (float) $pourcentage / 100));
    }

    public function libelleAudit(): string
    {
        return 'produit « '.$this->nom.' »';
    }

    protected static function newFactory(): ProduitFactory
    {
        return ProduitFactory::new();
    }
}
