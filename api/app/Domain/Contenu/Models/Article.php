<?php

namespace App\Domain\Contenu\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Enums\StatutArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Article de blog (CdC § 12, écran Paramètres › Divers, P1-BO-10).
 *
 * @property int $id
 * @property string $titre
 * @property string $slug
 * @property string|null $resume
 * @property string $contenu
 * @property string|null $image_url
 * @property StatutArticle $statut
 * @property Carbon|null $publie_le
 * @property int $auteur_id
 */
class Article extends Model
{
    use EstAudite;

    protected $guarded = [];

    protected $attributes = ['statut' => 'brouillon'];

    protected function casts(): array
    {
        return ['statut' => StatutArticle::class, 'publie_le' => 'datetime'];
    }

    public function libelleAudit(): string
    {
        return 'article « '.$this->titre.' »';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
