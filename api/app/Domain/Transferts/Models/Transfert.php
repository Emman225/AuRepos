<?php

namespace App\Domain\Transferts\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use Database\Factories\TransfertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Transfert et accompagnement (CdC § 6.6) : un transport avec chauffeur depuis l'aéroport, la
 * gare ou la ville jusqu'à la résidence (et le retour), demandé par le client PENDANT son séjour.
 * Le prix vient du barème (zone × type de véhicule), jamais saisi par le client. La clôture se
 * fait par le code de prise en charge que le chauffeur SAISIT (jamais lu).
 *
 * @property int $id
 * @property string $reference
 * @property int $sejour_id
 * @property string $lieu_de_prise_en_charge
 * @property int $commune_id
 * @property int $type_vehicule_souhaite_id
 * @property Carbon $date_heure_prevue
 * @property int $nombre_passagers
 * @property int $nombre_bagages
 * @property int $montant
 * @property EtatDuTransfert $etat
 * @property int|null $chauffeur_id
 * @property int|null $vehicule_id
 * @property int|null $montant_verse_au_chauffeur
 * @property string|null $notes
 * @property-read Sejour $sejour
 * @property-read Commune $commune
 * @property-read TypeVehicule $typeVehiculeSouhaite
 * @property-read Chauffeur|null $chauffeur
 * @property-read Vehicule|null $vehicule
 */
class Transfert extends Model
{
    /** @use HasFactory<TransfertFactory> */
    use EstAudite, HasFactory;

    protected $table = 'transferts';

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = ['etat' => 'demande', 'nombre_bagages' => 0];

    protected function casts(): array
    {
        return [
            'date_heure_prevue' => 'datetime', 'nombre_passagers' => 'integer', 'nombre_bagages' => 'integer',
            'montant' => 'integer', 'etat' => EtatDuTransfert::class, 'montant_verse_au_chauffeur' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Transfert $t) => $t->reference = 'TMP-'.Str::uuid()->toString());
        static::created(function (Transfert $t): void {
            $t->reference = 'TRF-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT);
            $t->saveQuietly();
        });
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** @return BelongsTo<TypeVehicule, $this> */
    public function typeVehiculeSouhaite(): BelongsTo
    {
        return $this->belongsTo(TypeVehicule::class, 'type_vehicule_souhaite_id');
    }

    /** @return BelongsTo<Chauffeur, $this> */
    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(Chauffeur::class);
    }

    /** @return BelongsTo<Vehicule, $this> */
    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function libelleAudit(): string
    {
        return 'transfert '.(str_starts_with($this->reference, 'TRF-') ? $this->reference.' ' : '')
            .'du '.$this->date_heure_prevue->format('d/m/Y H:i');
    }

    protected static function newFactory(): TransfertFactory
    {
        return TransfertFactory::new();
    }
}
