<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $logement_id
 * @property string $chemin_original
 * @property string $chemin_affichage
 * @property string $chemin_vignette
 * @property string|null $legende
 * @property int $ordre
 * @property bool $couverture
 * @property int $largeur
 * @property int $hauteur
 * @property int $taille_octets
 * @property int|null $ajoutee_par
 * @property bool $ajoutee_par_administration
 * @property string $etat
 * @property-read Logement $logement
 */
class PhotoLogement extends Model
{
    use EstAudite;

    protected $table = 'photos_logement';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['couverture' => 'boolean', 'ajoutee_par_administration' => 'boolean'];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** Le paramètre `v` change à chaque recadrage : le navigateur ne ressert pas l'ancienne image. */
    public function urlAffichage(): string
    {
        return Storage::disk('public')->url($this->chemin_affichage).'?v='.$this->updated_at?->getTimestamp();
    }

    public function urlVignette(): string
    {
        return Storage::disk('public')->url($this->chemin_vignette).'?v='.$this->updated_at?->getTimestamp();
    }

    public function libelleAudit(): string
    {
        return 'photo'.($this->legende ? ' « '.$this->legende.' »' : '').' du logement n° '.$this->logement_id;
    }
}
