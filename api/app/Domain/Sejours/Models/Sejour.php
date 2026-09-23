<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Assistance\Models\TicketAssistance;
use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Enums\PolitiqueAnnulation;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Repas\Models\Commande;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Transferts\Models\Transfert;
use Database\Factories\SejourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un séjour = un logement × une période × des occupants (CdC § 4). L'unité de facturation.
 *
 * @property int $id
 * @property string $reference
 * @property int $logement_id
 * @property int|null $client_id
 * @property Carbon $arrivee
 * @property Carbon $depart
 * @property int $adultes
 * @property int $enfants
 * @property EtatDuSejour $etat
 * @property string $canal
 * @property array<string, mixed>|null $devis
 * @property int $net_a_payer
 * @property float|null $reduction_pourcentage
 * @property string|null $reduction_motif
 * @property int $caution
 * @property Carbon|null $expire_le
 * @property string $mode_reglement
 * @property string|null $bon_de_commande
 * @property int $acompte_exige
 * @property int|null $prix_proprietaire_par_nuit
 * @property PolitiqueAnnulation $politique_annulation
 * @property float $annulation_pourcentage_retenu
 * @property int $annulation_delai_jours
 * @property Carbon|null $confirme_le
 * @property Carbon|null $annule_le
 * @property string|null $motif_annulation
 * @property int $montant_retenu_annulation
 * @property int|null $agent_accueil_id
 * @property Carbon|null $arrive_le
 * @property Carbon|null $no_show_le
 * @property Carbon|null $parti_le
 * @property int|null $checkout_par
 * @property int $caution_retenue
 * @property string|null $caution_retenue_motif
 * @property-read Logement $logement
 */
class Sejour extends Model
{
    /** @use HasFactory<SejourFactory> */
    use EstAudite, HasFactory;

    protected $table = 'sejours';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'demande', 'canal' => 'direct', 'mode_reglement' => 'agence', 'politique_annulation' => 'moderee', 'acompte_exige' => 0, 'montant_retenu_annulation' => 0, 'annulation_pourcentage_retenu' => 0, 'annulation_delai_jours' => 0, 'adultes' => 1, 'enfants' => 0, 'net_a_payer' => 0, 'caution' => 0];

    protected function casts(): array
    {
        return [
            'arrivee' => 'date', 'depart' => 'date', 'etat' => EtatDuSejour::class, 'devis' => 'array',
            'net_a_payer' => 'integer', 'reduction_pourcentage' => 'float', 'caution' => 'integer', 'expire_le' => 'datetime',
            'acompte_exige' => 'integer', 'prix_proprietaire_par_nuit' => 'integer', 'montant_retenu_annulation' => 'integer',
            'politique_annulation' => PolitiqueAnnulation::class, 'annulation_pourcentage_retenu' => 'float',
            'confirme_le' => 'datetime', 'annule_le' => 'datetime',
            'arrive_le' => 'datetime', 'no_show_le' => 'datetime', 'parti_le' => 'datetime',
            'caution_retenue' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Sejour $s) => $s->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (Sejour $s): void {
            $s->reference = 'SEJ-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT);
            $s->saveQuietly();
        });
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return HasMany<Occupant, $this> */
    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class);
    }

    /** @return HasOne<Avis, $this> */
    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }

    /** Transferts demandés pendant ce séjour (CdC § 6.6). @return HasMany<Transfert, $this> */
    public function transferts(): HasMany
    {
        return $this->hasMany(Transfert::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agentAccueil(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_accueil_id');
    }

    /** États des lieux d'entrée et de sortie (P2-SEJ-02) : au plus deux lignes. @return HasMany<EtatDesLieux, $this> */
    public function etatsDesLieux(): HasMany
    {
        return $this->hasMany(EtatDesLieux::class);
    }

    /** Demandes d'annulation instruites (P2-SEJ-06). @return HasMany<DemandeAnnulation, $this> */
    public function demandesAnnulation(): HasMany
    {
        return $this->hasMany(DemandeAnnulation::class);
    }

    /** Commandes de repas passées pendant ce séjour. @return HasMany<Commande, $this> */
    public function commandesRepas(): HasMany
    {
        return $this->hasMany(Commande::class);
    }

    /** Commandes d'extras passées pendant ce séjour (P2-EXT-01). @return HasMany<CommandeExtra, $this> */
    public function commandesExtras(): HasMany
    {
        return $this->hasMany(CommandeExtra::class);
    }

    /** Missions de ménage déclenchées par ce séjour (P2-MEN-01). @return HasMany<Mission, $this> */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /** Tickets d'assistance ouverts pendant ce séjour (P2-AST-01). @return HasMany<TicketAssistance, $this> */
    public function ticketsAssistance(): HasMany
    {
        return $this->hasMany(TicketAssistance::class);
    }

    /** Réclamations soulevées après ce séjour (P2-AST-01). @return HasMany<Reclamation, $this> */
    public function reclamations(): HasMany
    {
        return $this->hasMany(Reclamation::class);
    }

    public function nombreDeNuits(): int
    {
        return (int) $this->arrivee->diffInDays($this->depart);
    }

    public function libelleAudit(): string
    {
        return 'séjour '.(str_starts_with((string) $this->reference, 'SEJ-') ? $this->reference.' ' : '')
            .'du '.$this->arrivee->format('d/m/Y').' au '.$this->depart->format('d/m/Y');
    }

    protected static function newFactory(): SejourFactory
    {
        return SejourFactory::new();
    }
}
