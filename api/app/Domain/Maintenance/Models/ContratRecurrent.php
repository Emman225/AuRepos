<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Maintenance\Enums\PeriodiciteContrat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contrat récurrent d'un logement (P2-MNT-02) : nom, périodicité, prochain rappel — un
 * simple enregistrement de rappel, pas un module de gestion de contrats complet.
 *
 * @property int $id
 * @property int $logement_id
 * @property string $nom
 * @property PeriodiciteContrat $periodicite
 * @property Carbon $prochain_rappel
 * @property bool $actif
 * @property string|null $notes
 * @property-read Logement $logement
 */
class ContratRecurrent extends Model
{
    use EstAudite;

    protected $table = 'contrats_recurrents';

    protected $fillable = ['logement_id', 'nom', 'periodicite', 'prochain_rappel', 'actif', 'notes', 'cree_par'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['periodicite' => PeriodiciteContrat::class, 'prochain_rappel' => 'date', 'actif' => 'boolean'];
    }

    /** @return BelongsTo<Logement, $this> */
    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }

    public function libelleAudit(): string
    {
        return 'contrat récurrent « '.$this->nom.' » ('.$this->periodicite->libelle().')';
    }
}
