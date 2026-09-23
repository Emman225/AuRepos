<?php

namespace App\Domain\Assistance\Models;

use App\Domain\Assistance\Enums\EtatDeLaReclamation;
use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Réclamation (P2-AST-01, CdC § 6.1) : un client en soulève une APRÈS un séjour (parti ou
 * clôturé), motif d'au moins 15 caractères (validé côté contrôleur). Se ferme sans rien, ou
 * avec un avoir / geste commercial — double validation (`avoir_montant`, CdC § 6.1) : un
 * administrateur propose, LE trésorier désigné confirme, comme `ReductionSurSejour`.
 *
 * @property int $id
 * @property int $sejour_id
 * @property int $client_id
 * @property string $motif
 * @property EtatDeLaReclamation $statut
 * @property string|null $reponse
 * @property int|null $avoir_montant
 * @property string|null $avoir_motif
 * @property int|null $reglement_id
 * @property int|null $fermee_par
 * @property Carbon|null $fermee_le
 * @property-read Sejour $sejour
 * @property-read User $client
 * @property-read Reglement|null $reglement
 */
class Reclamation extends Model
{
    use EstAudite;

    protected $fillable = [
        'sejour_id', 'client_id', 'motif', 'statut', 'reponse', 'avoir_montant', 'avoir_motif',
        'reglement_id', 'fermee_par', 'fermee_le',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'ouverte'];

    protected function casts(): array
    {
        return ['statut' => EtatDeLaReclamation::class, 'fermee_le' => 'datetime'];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Reglement, $this> */
    public function reglement(): BelongsTo
    {
        return $this->belongsTo(Reglement::class);
    }

    public function fermee(): bool
    {
        return $this->statut === EtatDeLaReclamation::Fermee;
    }

    public function libelleAudit(): string
    {
        return 'réclamation sur le '.$this->sejour->libelleAudit();
    }
}
