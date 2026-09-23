<?php

namespace App\Domain\Repas\Models;

use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Sejours\Models\Sejour;
use Database\Factories\CommandeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Une commande de repas et boissons, livrée au logement d'un séjour EN COURS (CdC —
 * « Repas et boissons »). Ne se rattache jamais à une réservation : un séjour doit déjà
 * exister ; « pendant le séjour », jamais au moment de la réservation.
 *
 * `offert` (P4-API-08) : extra CdC § 5.2 « premier repas livré à l'arrivée » — une commande
 * comme une autre (même circuit préparation/livraison), sauf que chaque ligne est à prix
 * nul pour le client (`montant_total` = 0) ; le restaurateur, lui, reste dû à son prix
 * normal (App\Domain\Repas\Services\GestionDesCommandes::detteEnversLeRestaurateur).
 *
 * @property int $id
 * @property string $reference
 * @property int $sejour_id
 * @property int $restaurateur_id
 * @property EtatDeCommande $etat
 * @property string $mode_reglement
 * @property int $montant_total
 * @property bool $offert
 * @property int|null $livreur_id
 * @property int|null $remuneration_livreur
 * @property string|null $notes
 * @property-read Sejour $sejour
 * @property-read Restaurateur $restaurateur
 * @property-read Livreur|null $livreur
 */
class Commande extends Model
{
    /** @use HasFactory<CommandeFactory> */
    use EstAudite, HasFactory;

    protected $table = 'commandes_repas';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'demande', 'offert' => false];

    protected function casts(): array
    {
        return [
            'etat' => EtatDeCommande::class,
            'montant_total' => 'integer',
            'offert' => 'boolean',
            'remuneration_livreur' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Commande $c) => $c->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (Commande $c): void {
            $c->reference = 'CMD-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT);
            $c->saveQuietly();
        });
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Restaurateur, $this> */
    public function restaurateur(): BelongsTo
    {
        return $this->belongsTo(Restaurateur::class);
    }

    /** @return BelongsTo<Livreur, $this> */
    public function livreur(): BelongsTo
    {
        return $this->belongsTo(Livreur::class);
    }

    /** @return HasMany<LigneDeCommande, $this> */
    public function lignes(): HasMany
    {
        return $this->hasMany(LigneDeCommande::class, 'commande_id');
    }

    /** Réclamations soulevées sur cette commande (P4-API-08). @return HasMany<Reclamation, $this> */
    public function reclamations(): HasMany
    {
        return $this->hasMany(Reclamation::class);
    }

    public function libelleAudit(): string
    {
        return 'commande '.(str_starts_with($this->reference, 'CMD-') ? $this->reference.' ' : '').'de repas';
    }

    protected static function newFactory(): CommandeFactory
    {
        return CommandeFactory::new();
    }
}
