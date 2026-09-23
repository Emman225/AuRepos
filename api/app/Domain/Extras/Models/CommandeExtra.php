<?php

namespace App\Domain\Extras\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Enums\EtatDeCommandeExtra;
use App\Domain\Sejours\Models\Sejour;
use Database\Factories\CommandeExtraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Une commande d'extra, passée PENDANT un séjour déjà arrivé (P2-EXT-01, même règle que
 * App\Domain\Assistance\Services\TicketsAssistance). Nom et prix sont FIGÉS au moment de la
 * commande : un extra renommé ou re-tarifé ensuite ne change jamais une commande déjà passée.
 *
 * @property int $id
 * @property string $reference
 * @property int $sejour_id
 * @property int $extra_id
 * @property int $quantite
 * @property string $nom_extra
 * @property int $prix_unitaire
 * @property int $montant_total
 * @property EtatDeCommandeExtra $etat
 * @property int|null $affecte_a_id
 * @property Carbon|null $affecte_le
 * @property Carbon|null $fournie_le
 * @property int|null $demande_par_id
 * @property string|null $notes
 * @property-read Sejour $sejour
 * @property-read Extra $extra
 * @property-read User|null $affecteA
 * @property-read User|null $demandePar
 */
class CommandeExtra extends Model
{
    /** @use HasFactory<CommandeExtraFactory> */
    use EstAudite, HasFactory;

    protected $table = 'commandes_extras';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'demande', 'quantite' => 1];

    protected function casts(): array
    {
        return [
            'etat' => EtatDeCommandeExtra::class, 'quantite' => 'integer',
            'prix_unitaire' => 'integer', 'montant_total' => 'integer',
            'affecte_le' => 'datetime', 'fournie_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (CommandeExtra $c) => $c->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (CommandeExtra $c): void {
            $c->reference = 'EXT-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT);
            $c->saveQuietly();
        });
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Extra, $this> */
    public function extra(): BelongsTo
    {
        return $this->belongsTo(Extra::class);
    }

    /** @return BelongsTo<User, $this> */
    public function affecteA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affecte_a_id');
    }

    /** @return BelongsTo<User, $this> */
    public function demandePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par_id');
    }

    public function libelleAudit(): string
    {
        return 'commande '.(str_starts_with($this->reference, 'EXT-') ? $this->reference.' ' : '').'d’extra';
    }

    protected static function newFactory(): CommandeExtraFactory
    {
        return CommandeExtraFactory::new();
    }
}
