<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\TypeLogement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prix négocié d'un client pour un type de logement (CdC § 7.3) : « ils priment sur tout
 * le reste » — la grille tarifaire et le prix de vente s'effacent devant ce tarif nuitée.
 *
 * @property int $id
 * @property int $client_id
 * @property int $type_logement_id
 * @property int $tarif_par_nuit
 * @property bool $actif
 * @property string|null $notes
 * @property int $modifie_par
 */
class PrixNegocie extends Model
{
    use EstAudite;

    protected $table = 'prix_negocies';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    protected function casts(): array
    {
        return ['tarif_par_nuit' => 'integer', 'actif' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<TypeLogement, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeLogement::class, 'type_logement_id');
    }

    public function libelleAudit(): string
    {
        return 'prix négocié de '.$this->client->nomComplet().' pour '.$this->type->nom;
    }
}
