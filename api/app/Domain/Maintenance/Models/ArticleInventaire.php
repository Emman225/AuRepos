<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Article d'inventaire d'un logement (P2-MNT-02) : nom, quantité, valeur de remplacement —
 * une simple liste, pas un module de gestion de stock.
 *
 * @property int $id
 * @property int $logement_id
 * @property string $nom
 * @property int $quantite
 * @property int|null $valeur_remplacement
 * @property-read Logement $logement
 */
class ArticleInventaire extends Model
{
    use EstAudite;

    protected $table = 'articles_inventaire';

    protected $fillable = ['logement_id', 'nom', 'quantite', 'valeur_remplacement', 'cree_par'];

    /** @var array<string, mixed> */
    protected $attributes = ['quantite' => 1];

    protected function casts(): array
    {
        return ['quantite' => 'integer', 'valeur_remplacement' => 'integer'];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    public function libelleAudit(): string
    {
        return 'article d’inventaire « '.$this->nom.' » ('.$this->quantite.')';
    }
}
