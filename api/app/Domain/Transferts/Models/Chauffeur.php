<?php

namespace App\Domain\Transferts\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Database\Factories\ChauffeurFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fiche du chauffeur (CdC § 6.6) : transporte le client pour un transfert, saisit son code
 * de prise en charge pour clôturer, gagne un montant saisi manuellement par le gestionnaire
 * à l'affectation (le CdC ne donne aucune formule automatique, contrairement à l'apporteur).
 *
 * @property int $id
 * @property int $user_id
 * @property bool $actif
 * @property-read User $utilisateur
 */
class Chauffeur extends Model
{
    /** @use HasFactory<ChauffeurFactory> */
    use EstAudite, HasFactory;

    protected $table = 'chauffeurs';

    protected $fillable = ['user_id', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Vehicule, $this> */
    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    /** @return HasMany<Transfert, $this> */
    public function transferts(): HasMany
    {
        return $this->hasMany(Transfert::class);
    }

    public function nomAffiche(): string
    {
        return $this->utilisateur->nomComplet();
    }

    public function libelleAudit(): string
    {
        return 'chauffeur « '.$this->nomAffiche().' »';
    }

    protected static function newFactory(): ChauffeurFactory
    {
        return ChauffeurFactory::new();
    }
}
