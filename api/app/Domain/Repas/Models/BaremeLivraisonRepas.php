<?php

namespace App\Domain\Repas\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Residence;
use Database\Factories\BaremeLivraisonRepasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barème de livraison repas (CdC — « Forfait par résidence… rémunération du livreur par
 * grille propre ou forfait, plancher par course »). Un seul forfait par résidence : la
 * variante « par zone » du CdC n'est pas construite ici (choix délibéré, voir le rapport
 * de la tâche) — elle aurait exigé une règle de résolution résidence-vs-zone qu'aucun
 * texte du CdC ne tranche.
 *
 * Ce barème est purement informatif : la rémunération versée au livreur est TOUJOURS
 * saisie manuellement à l'affectation (App\Domain\Repas\Services\GestionDesCommandes::
 * affecterUnLivreur), jamais calculée automatiquement depuis ce forfait.
 *
 * @property int $id
 * @property int $residence_id
 * @property int $forfait
 * @property-read Residence $residence
 */
class BaremeLivraisonRepas extends Model
{
    /** @use HasFactory<BaremeLivraisonRepasFactory> */
    use EstAudite, HasFactory;

    protected $table = 'baremes_livraison_repas';

    protected $fillable = ['residence_id', 'forfait'];

    protected function casts(): array
    {
        return ['forfait' => 'integer'];
    }

    /** @return BelongsTo<Residence, $this> */
    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    public function libelleAudit(): string
    {
        return 'barème de livraison repas de '.$this->residence->nom;
    }

    protected static function newFactory(): BaremeLivraisonRepasFactory
    {
        return BaremeLivraisonRepasFactory::new();
    }
}
