<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Modification d'un logement PUBLIÉ, en attente de validation : la version publiée reste en
 * ligne tant que celle-ci n'est pas validée (CdC § 7.1). Ne porte que les champs descriptifs
 * (nom, description, capacité, règles...) — le prix a la négociation, le prix de vente la
 * double validation, les photos leur propre circuit d'acceptation.
 *
 * @property int $id
 * @property int $logement_id
 * @property array<string, mixed> $valeurs
 * @property string $statut
 * @property int $propose_par
 * @property int|null $decide_par
 * @property Carbon|null $decide_le
 * @property string|null $motif_refus
 * @property-read Logement $logement
 * @property-read User $auteur
 * @property-read User|null $decideur
 */
class VersionLogement extends Model
{
    use EstAudite;

    protected $table = 'versions_logement';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['statut' => 'en_attente'];

    protected function casts(): array
    {
        return ['valeurs' => 'array', 'decide_le' => 'datetime'];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propose_par');
    }

    /** @return BelongsTo<User, $this> */
    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }

    public function enAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    public function libelleAudit(): string
    {
        return 'version en attente du '.$this->logement->libelleAudit();
    }
}
