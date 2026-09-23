<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande de paiement d'un propriétaire, plafonnée à son solde net (CdC § 8.7). Le décaissement
 * réutilise App\Domain\Caisse\Services\Caisse::saisirUnDecaissement — `reglement_id` n'est posé
 * qu'une fois ce décaissement saisi ; le circuit de preuve de la caisse continue ensuite seul.
 *
 * @property int $id
 * @property int $proprietaire_id
 * @property int $montant
 * @property int|null $montant_brut_equivalent
 * @property float|null $retenue_taux
 * @property int|null $retenue_montant
 * @property string|null $retenue_motif
 * @property string $etat
 * @property int $demande_par
 * @property Carbon $demande_le
 * @property int|null $decide_par
 * @property Carbon|null $decide_le
 * @property string|null $motif_rejet
 * @property int|null $reglement_id
 * @property-read Proprietaire $proprietaire
 * @property-read Reglement|null $reglement
 */
class DemandePaiementProprietaire extends Model
{
    use EstAudite;

    protected $table = 'demandes_paiement_proprietaire';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer', 'montant_brut_equivalent' => 'integer', 'retenue_taux' => 'float',
            'retenue_montant' => 'integer', 'demande_le' => 'datetime', 'decide_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Proprietaire, $this> */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    public function enAttente(): bool
    {
        return $this->etat === 'en_attente';
    }

    public function libelleAudit(): string
    {
        return 'demande de paiement de '.number_format($this->montant, 0, ',', ' ').' F du propriétaire n° '.$this->proprietaire_id;
    }
}
