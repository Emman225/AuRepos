<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDeLaDemandeAnnulation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande d'annulation d'un séjour confirmé (ou déjà arrivé), instruite par un administrateur
 * (P2-SEJ-06, CdC § 6.1) — distincte de l'annulation directe d'une simple demande, que le
 * client fait déjà lui-même (`App\Http\Controllers\Api\V1\Client\SejoursController::annuler`).
 *
 * @property int $id
 * @property int $sejour_id
 * @property int $demandee_par
 * @property string $motif_client
 * @property EtatDeLaDemandeAnnulation $etat
 * @property int|null $montant_retenu
 * @property int|null $montant_rembourse
 * @property int|null $reglement_id
 * @property string|null $motif_decision
 * @property int|null $instruite_par
 * @property Carbon|null $instruite_le
 * @property-read Sejour $sejour
 */
class DemandeAnnulation extends Model
{
    use EstAudite;

    protected $table = 'demandes_annulation';

    protected $fillable = [
        'sejour_id', 'demandee_par', 'motif_client', 'etat', 'montant_retenu', 'montant_rembourse',
        'reglement_id', 'motif_decision', 'instruite_par', 'instruite_le',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'en_attente'];

    protected function casts(): array
    {
        return ['etat' => EtatDeLaDemandeAnnulation::class, 'instruite_le' => 'datetime'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandee_par');
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    public function enAttente(): bool
    {
        return $this->etat === EtatDeLaDemandeAnnulation::EnAttente;
    }

    public function libelleAudit(): string
    {
        return 'demande d’annulation du '.$this->sejour->libelleAudit();
    }
}
