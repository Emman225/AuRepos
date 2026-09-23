<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Partenaires\Models\PieceJustificative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Une ligne d'inventaire (un élément, un compteur…) d'un état des lieux (P2-SEJ-02).
 *
 * La comparaison d'une ligne de SORTIE avec son équivalent d'ENTRÉE se fait par libellé
 * identique (`ligneDEntreeCorrespondante()`) : jamais une clé étrangère entre deux lignes
 * saisies à des moments différents, par des agents différents.
 *
 * @property int $id
 * @property int $etat_des_lieux_id
 * @property string $libelle
 * @property string|null $observation
 * @property int $ordre
 * @property-read EtatDesLieux $etatDesLieux
 */
class LigneEtatDesLieux extends Model
{
    use EstAudite;

    protected $table = 'lignes_etat_des_lieux';

    protected $fillable = ['etat_des_lieux_id', 'libelle', 'observation', 'ordre'];

    /** @return BelongsTo<EtatDesLieux, $this> */
    public function etatDesLieux(): BelongsTo
    {
        return $this->belongsTo(EtatDesLieux::class, 'etat_des_lieux_id');
    }

    /** Photos de cette ligne : même mécanisme chiffré que les pièces d'un dossier (P1-CAT-03). */
    public function photos(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'titulaire');
    }

    public function libelleAudit(): string
    {
        return 'ligne « '.$this->libelle.' » de l’'.$this->etatDesLieux->type->libelle();
    }
}
