<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Maintenance\Enums\EtatDuTicketMaintenance;
use App\Domain\Maintenance\Enums\ImputationCout;
use App\Domain\Maintenance\Enums\OrigineDuTicket;
use App\Domain\Maintenance\Enums\UrgenceTicket;
use App\Domain\Sejours\Models\BlocageCalendrier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ticket de maintenance (P2-MNT-01, CdC § 6.4) : origine, urgence, technicien (texte libre —
 * aucun profil dédié n'existe), coûts imputables. Un ticket « bloquant » retire le logement
 * du calendrier en posant un `BlocageCalendrier` (motif « maintenance ») — `blocage_calendrier_id`
 * trace ce blocage pour le lever à la résolution.
 *
 * @property int $id
 * @property int $logement_id
 * @property int|null $mission_id
 * @property OrigineDuTicket $origine
 * @property UrgenceTicket $urgence
 * @property string $description
 * @property string|null $technicien_nom
 * @property string|null $technicien_contact
 * @property EtatDuTicketMaintenance $statut
 * @property int|null $cout_montant
 * @property ImputationCout|null $cout_impute_a
 * @property Carbon|null $indisponible_jusquau
 * @property int|null $blocage_calendrier_id
 * @property int|null $signalee_par
 * @property int|null $resolue_par
 * @property Carbon|null $resolue_le
 * @property-read Logement $logement
 * @property-read Mission|null $mission
 * @property-read BlocageCalendrier|null $blocageCalendrier
 */
class TicketMaintenance extends Model
{
    use EstAudite;

    protected $table = 'tickets_maintenance';

    protected $fillable = [
        'logement_id', 'mission_id', 'origine', 'urgence', 'description', 'technicien_nom', 'technicien_contact',
        'statut', 'cout_montant', 'cout_impute_a', 'indisponible_jusquau', 'blocage_calendrier_id',
        'signalee_par', 'resolue_par', 'resolue_le',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'ouvert'];

    protected function casts(): array
    {
        return [
            'origine' => OrigineDuTicket::class,
            'urgence' => UrgenceTicket::class,
            'statut' => EtatDuTicketMaintenance::class,
            'cout_montant' => 'integer',
            'cout_impute_a' => ImputationCout::class,
            'indisponible_jusquau' => 'date',
            'resolue_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** @return BelongsTo<Mission, $this> */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    /** @return BelongsTo<BlocageCalendrier, $this> */
    public function blocageCalendrier(): BelongsTo
    {
        return $this->belongsTo(BlocageCalendrier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function signalePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signalee_par');
    }

    /** @return BelongsTo<User, $this> */
    public function resoluPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolue_par');
    }

    public function libelleAudit(): string
    {
        return 'ticket de maintenance « '.($this->relationLoaded('logement') ? $this->logement->nom : $this->logement_id).' » ('.$this->urgence->libelle().')';
    }
}
