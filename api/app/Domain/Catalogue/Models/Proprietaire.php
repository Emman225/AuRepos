<?php

namespace App\Domain\Catalogue\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Enums\NatureJuridique;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Enums\TypeDePiece;
use App\Domain\Partenaires\Models\PieceJustificative;
use Database\Factories\ProprietaireFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fiche du propriétaire (l'ex-fournisseur de Mon Gravier). L'entreprise possède
 * elle aussi un compte propriétaire, marqué « interne », pour ses propres résidences :
 * même circuit, mais reversement interne, sans retenue à la source (CdC § 7.1).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $raison_sociale
 * @property bool $interne
 * @property NatureJuridique $nature
 * @property RegimeFiscal $regime_fiscal
 * @property bool $assujetti_tva
 * @property string|null $ncc
 * @property string|null $rccm
 * @property string|null $adresse
 * @property ModeDeRemuneration $mode_remuneration
 * @property string|null $taux_commission
 * @property string|null $part_entreprise_cautions
 * @property Carbon|null $mandat_signe_le
 * @property Carbon|null $mandat_expire_le
 * @property bool $bons_valides_automatiquement
 * @property string|null $notes
 * @property-read User $utilisateur
 */
class Proprietaire extends Model
{
    /** @use HasFactory<ProprietaireFactory> */
    use EstAudite, HasFactory;

    protected $table = 'proprietaires';

    protected $fillable = [
        'user_id', 'raison_sociale', 'interne', 'nature', 'regime_fiscal', 'assujetti_tva', 'ncc', 'rccm', 'adresse',
        'mode_remuneration', 'taux_commission', 'part_entreprise_cautions', 'mandat_signe_le', 'mandat_expire_le',
        'bons_valides_automatiquement', 'notes',
    ];

    /**
     * Mêmes valeurs par défaut que la base, connues du modèle dès sa création : sans elles,
     * un propriétaire tout juste créé aurait un régime fiscal « null » jusqu'à sa relecture.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'interne' => false,
        'nature' => 'personne_physique',
        'regime_fiscal' => 'non_renseigne',
        'assujetti_tva' => false,
        'mode_remuneration' => 'prix_negocie',
        'bons_valides_automatiquement' => false,
    ];

    protected function casts(): array
    {
        return [
            'interne' => 'boolean',
            'nature' => NatureJuridique::class,
            'regime_fiscal' => RegimeFiscal::class,
            'assujetti_tva' => 'boolean',
            'mode_remuneration' => ModeDeRemuneration::class,
            'mandat_signe_le' => 'date',
            'mandat_expire_le' => 'date',
            'bons_valides_automatiquement' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<Residence, $this> */
    public function residences(): HasMany
    {
        return $this->hasMany(Residence::class);
    }

    /** @return MorphMany<PieceJustificative, $this> */
    public function pieces(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'titulaire');
    }

    public function nomAffiche(): string
    {
        $nom = $this->raison_sociale ?: $this->utilisateur->nomComplet();

        return $this->interne ? $nom.' (propriétaire interne)' : $nom;
    }

    /** Le régime réel ne dispense de retenue que s'il est JUSTIFIÉ par une pièce validée (CdC § 8.7). */
    public function regimeReelJustifie(): bool
    {
        return $this->regime_fiscal->estUnRegimeReel()
            && $this->piecesValables()->contains(fn (PieceJustificative $p) => $p->type->justifieLeRegime());
    }

    /**
     * Ce qui manque pour que le dossier soit complet (CdC § 7.2) : pièce d'identité,
     * titre de propriété OU bail, RIB, mandat signé, et régime fiscal renseigné.
     * Le compte interne de l'entreprise n'a rien à justifier.
     *
     * @return list<string>
     */
    public function elementsManquants(): array
    {
        if ($this->interne) {
            return [];
        }

        $types = $this->piecesValables()->map(fn (PieceJustificative $p) => $p->type)->all();
        $a = fn (TypeDePiece ...$parmi): bool => array_intersect(array_map(fn ($t) => $t->value, $parmi), array_map(fn ($t) => $t->value, $types)) !== [];

        return array_values(array_filter([
            $a(TypeDePiece::PieceIdentite) ? null : TypeDePiece::PieceIdentite->libelle(),
            $a(TypeDePiece::TitrePropriete, TypeDePiece::Bail) ? null : 'Titre de propriété ou bail',
            $a(TypeDePiece::Rib) ? null : TypeDePiece::Rib->libelle(),
            $a(TypeDePiece::Mandat) && $this->mandat_signe_le !== null ? null : TypeDePiece::Mandat->libelle(),
            $this->regime_fiscal === RegimeFiscal::NonRenseigne ? 'Régime fiscal' : null,
            $this->regime_fiscal->estUnRegimeReel() && ! $this->regimeReelJustifie() ? 'Justificatif du régime réel (DFE ou attestation)' : null,
            $this->assujetti_tva && ! $this->ncc ? 'NCC (obligatoire pour un assujetti à la TVA)' : null,
        ]));
    }

    public function dossierComplet(): bool
    {
        return $this->elementsManquants() === [];
    }

    public function libelleAudit(): string
    {
        return 'propriétaire « '.$this->nomAffiche().' »';
    }

    /** @return Collection<int, PieceJustificative> */
    private function piecesValables(): Collection
    {
        return $this->pieces->filter(fn (PieceJustificative $p) => $p->estValable())->values();
    }

    protected static function newFactory(): ProprietaireFactory
    {
        return ProprietaireFactory::new();
    }
}
