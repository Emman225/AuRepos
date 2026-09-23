<?php

namespace App\Domain\Caisse\Models;

use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Part d'un règlement affectée à une affaire (un séjour aujourd'hui ; extras, transferts, repas demain).
 *
 * @property int $id
 * @property int $reglement_id
 * @property string $affaire_type
 * @property int $affaire_id
 * @property int $montant
 * @property-read Reglement $reglement
 */
class Imputation extends Model
{
    protected $table = 'imputations_reglement';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    /** @return MorphTo<Model, $this> */
    public function affaire(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Le libellé de l'affaire imputée, pour le reçu (App\Domain\Caisse\Services\Recus) et la
     * fiche back office (App\Http\Resources\Backoffice\ReglementResource) — un seul endroit
     * pour ne jamais désaccorder les deux.
     */
    public function libelleDeLAffaire(): string
    {
        return match (true) {
            $this->affaire instanceof Sejour => 'Séjour '.$this->affaire->reference.' — du '.$this->affaire->arrivee->format('d/m/Y').' au '.$this->affaire->depart->format('d/m/Y'),
            $this->affaire instanceof Transfert => 'Transfert '.$this->affaire->reference.' du '.$this->affaire->date_heure_prevue->format('d/m/Y H:i'),
            $this->affaire instanceof CommandeExtra => 'Extra '.$this->affaire->reference.' — '.$this->affaire->nom_extra,
            default => $this->affaire_type.' n° '.$this->affaire_id,
        };
    }
}
