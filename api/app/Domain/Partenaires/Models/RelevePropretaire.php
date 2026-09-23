<?php

namespace App\Domain\Partenaires\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Relevé mensuel d'un propriétaire (CdC § 7.2 et § 8.7) : nuitées consommées, montant brut,
 * charges refacturées, part des cautions retenues, TVA de l'assujetti, retenue à la source
 * FIGÉE à sa génération, net à reverser. Sert aussi d'attestation de retenue mensuelle.
 *
 * @property int $id
 * @property int $proprietaire_id
 * @property Carbon $periode
 * @property int $nuitees_consommees
 * @property int $montant_brut
 * @property int $charges_refacturees
 * @property int $part_cautions
 * @property int $tva
 * @property float $retenue_taux
 * @property int $retenue_montant
 * @property string|null $retenue_motif
 * @property int $montant_net
 * @property string|null $chemin_pdf
 * @property string|null $chemin_attestation_pdf
 * @property Carbon $genere_le
 * @property Carbon|null $envoye_le
 * @property-read Proprietaire $proprietaire
 */
class RelevePropretaire extends Model
{
    use EstAudite;

    protected $table = 'releves_proprietaire';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'periode' => 'date', 'nuitees_consommees' => 'integer', 'montant_brut' => 'integer',
            'charges_refacturees' => 'integer', 'part_cautions' => 'integer', 'tva' => 'integer',
            'retenue_taux' => 'float', 'retenue_montant' => 'integer', 'montant_net' => 'integer',
            'genere_le' => 'datetime', 'envoye_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Proprietaire, $this> */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(Proprietaire::class);
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }

    /** Les charges effectivement reprises sur ce relevé. @return HasMany<ChargeProprietaire, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(ChargeProprietaire::class, 'releve_id');
    }

    public function libelleAudit(): string
    {
        return 'relevé '.$this->periode->format('m/Y').' du propriétaire n° '.$this->proprietaire_id;
    }
}
