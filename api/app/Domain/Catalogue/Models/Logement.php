<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Enums\EtatMenage;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Enums\PolitiqueAnnulation;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Maintenance\Models\ArticleInventaire;
use App\Domain\Maintenance\Models\ContratRecurrent;
use App\Domain\Maintenance\Models\TicketMaintenance;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Sejours\Enums\StatutAvis;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Models\Sejour;
use Database\Factories\LogementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * L'unité vendue : jamais deux séjours la même nuit sur un même logement.
 *
 * @property int $id
 * @property int $residence_id
 * @property int $type_logement_id
 * @property string $nom
 * @property string $reference
 * @property int $nombre_pieces
 * @property int $nombre_chambres
 * @property int $capacite_de_base
 * @property int $capacite_maximale
 * @property int $caution
 * @property int|null $duree_minimale
 * @property int|null $duree_maximale
 * @property PolitiqueAnnulation $politique_annulation
 * @property int|null $prix_proprietaire
 * @property int|null $prix_vente
 * @property float|null $pourcentage_entreprise_derogation
 * @property EtatPublication $etat_publication
 * @property Carbon|null $publie_le
 * @property bool $mise_en_avant
 * @property EtatMenage|null $etat_menage
 * @property-read Residence $residence
 * @property-read TypeLogement $type
 */
class Logement extends Model
{
    /** @use HasFactory<LogementFactory> */
    use EstAudite, HasFactory, SoftDeletes;

    protected $table = 'logements';

    protected $fillable = [
        'residence_id', 'type_logement_id', 'nom', 'nombre_pieces', 'nombre_chambres', 'nombre_lits',
        'nombre_salles_de_bain', 'capacite_de_base', 'capacite_maximale', 'surface_m2', 'description',
        'fumeur_autorise', 'animaux_autorises', 'fetes_autorisees', 'regles_maison',
        'heure_arrivee', 'heure_depart', 'caution', 'duree_minimale', 'duree_maximale', 'politique_annulation',
        'mise_en_avant', 'etat_menage',
    ];

    protected function casts(): array
    {
        return [
            'etat_publication' => EtatPublication::class,
            'etat_menage' => EtatMenage::class,
            'politique_annulation' => PolitiqueAnnulation::class,
            'fumeur_autorise' => 'boolean',
            'animaux_autorises' => 'boolean',
            'fetes_autorisees' => 'boolean',
            'mise_en_avant' => 'boolean',
            'caution' => 'integer',
            'prix_proprietaire' => 'integer',
            'prix_vente' => 'integer',
            'pourcentage_entreprise_derogation' => 'float',
            'publie_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Référence courte et stable, imprimée sur les fiches d'arrivée et les états des lieux.
        // Elle dérive de l'identifiant RÉEL de la ligne : calculer « le plus grand + 1 » avant
        // l'insertion donnerait la même référence à deux logements créés au même instant.
        static::creating(function (Logement $logement): void {
            $logement->reference = 'TMP-'.Str::uuid()->toString();
        });

        static::created(function (Logement $logement): void {
            $logement->reference = 'LOG-'.str_pad((string) $logement->id, 5, '0', STR_PAD_LEFT);
            $logement->saveQuietly();
        });
    }

    /** @return BelongsTo<Residence, $this> */
    public function residence(): BelongsTo
    {
        return $this->belongsTo(Residence::class);
    }

    /** @return BelongsTo<TypeLogement, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TypeLogement::class, 'type_logement_id');
    }

    /** @return BelongsToMany<Equipement, $this> */
    public function equipements(): BelongsToMany
    {
        return $this->belongsToMany(Equipement::class, 'equipement_logement');
    }

    /** @return HasMany<PhotoLogement, $this> */
    public function photos(): HasMany
    {
        // Pas de tri ici : PostgreSQL refuse un ORDER BY dans un COUNT ou un MAX. On trie à la lecture.
        return $this->hasMany(PhotoLogement::class);
    }

    /**
     * Sert au rattachement « un blocage ne se trouve que dans son logement » des routes.
     *
     * @return HasMany<BlocageCalendrier, $this>
     */
    public function blocages(): HasMany
    {
        return $this->hasMany(BlocageCalendrier::class);
    }

    /** Missions de ménage sur ce logement (P2-MEN-01). @return HasMany<Mission, $this> */
    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }

    /** Tickets de maintenance de ce logement (P2-MNT-01). @return HasMany<TicketMaintenance, $this> */
    public function ticketsMaintenance(): HasMany
    {
        return $this->hasMany(TicketMaintenance::class);
    }

    /** Inventaire de ce logement (P2-MNT-02). @return HasMany<ArticleInventaire, $this> */
    public function articlesInventaire(): HasMany
    {
        return $this->hasMany(ArticleInventaire::class);
    }

    /** Contrats récurrents de ce logement (P2-MNT-02). @return HasMany<ContratRecurrent, $this> */
    public function contratsRecurrents(): HasMany
    {
        return $this->hasMany(ContratRecurrent::class);
    }

    /** Avis PUBLIÉS des séjours de ce logement (P2-AVI-01) — jamais ceux en attente ou refusés.
     *
     * @return HasManyThrough<Avis, Sejour, $this>
     */
    public function avisPublies(): HasManyThrough
    {
        return $this->hasManyThrough(Avis::class, Sejour::class)->where('avis.statut', StatutAvis::Publie);
    }

    /** « Appartement 3 pièces, 2 chambres » — le résumé des vignettes du site (CdC § 5.1). */
    public function resume(): string
    {
        $chambres = $this->nombre_chambres > 0
            ? ', '.$this->nombre_chambres.' chambre'.($this->nombre_chambres > 1 ? 's' : '')
            : '';

        return $this->type->nom.$chambres;
    }

    public function libelleAudit(): string
    {
        // À la création, la trace s'écrit avant que la référence définitive soit posée.
        $reference = str_starts_with((string) $this->reference, 'LOG-') ? ' ('.$this->reference.')' : '';

        return 'logement « '.$this->nom.' »'.$reference;
    }

    protected static function newFactory(): LogementFactory
    {
        return LogementFactory::new();
    }
}
