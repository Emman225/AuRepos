<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Charge refacturée à un propriétaire (ménage, réparation...) : vient en déduction de son
 * relevé du mois où elle est imputée (CdC § 7.2 : « le reversement porte sur les nuitées
 * réellement consommées ... moins les charges imputables »). `releve_id` n'est renseigné
 * qu'une fois reprise sur un relevé généré, pour ne jamais la déduire deux fois.
 *
 * @property int $id
 * @property int $proprietaire_id
 * @property int|null $logement_id
 * @property Carbon $periode
 * @property string $nature
 * @property int $montant
 * @property string $motif
 * @property int $cree_par
 * @property int|null $releve_id
 * @property-read Proprietaire $proprietaire
 * @property-read Logement|null $logement
 */
class ChargeProprietaire extends Model
{
    use EstAudite;

    public const NATURES = ['menage' => 'Ménage', 'reparation' => 'Réparation', 'autre' => 'Autre'];

    protected $table = 'charges_proprietaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['periode' => 'date', 'montant' => 'integer'];
    }

    /** @return BelongsTo<Proprietaire, $this> */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function reprise(): bool
    {
        return $this->releve_id !== null;
    }

    public function libelleAudit(): string
    {
        return 'charge « '.(self::NATURES[$this->nature] ?? $this->nature).' » de '
            .number_format($this->montant, 0, ',', ' ').' F sur le propriétaire n° '.$this->proprietaire_id;
    }
}
