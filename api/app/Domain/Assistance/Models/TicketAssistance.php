<?php

namespace App\Domain\Assistance\Models;

use App\Domain\Assistance\Enums\EtatDuTicket;
use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ticket d'assistance (P2-AST-01, CdC § 6.1) : un client en soulève un PENDANT un séjour
 * actif (état « arrivé »), l'espace assistance (Profil::AgentAssistance) répond et ferme.
 *
 * @property int $id
 * @property int $sejour_id
 * @property int $client_id
 * @property string $sujet
 * @property string $message
 * @property EtatDuTicket $statut
 * @property string|null $reponse
 * @property int|null $traite_par
 * @property Carbon|null $traite_le
 * @property-read Sejour $sejour
 * @property-read User $client
 */
class TicketAssistance extends Model
{
    use EstAudite;

    protected $table = 'tickets_assistance';

    protected $fillable = ['sejour_id', 'client_id', 'sujet', 'message', 'statut', 'reponse', 'traite_par', 'traite_le'];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'ouvert'];

    protected function casts(): array
    {
        return ['statut' => EtatDuTicket::class, 'traite_le' => 'datetime'];
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

    public function libelleAudit(): string
    {
        return 'ticket d’assistance « '.$this->sujet.' » du '.$this->sejour->libelleAudit();
    }
}
