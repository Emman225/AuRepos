<?php

namespace App\Domain\Fiscalite\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Fiscalite\Enums\StatutDeTransmissionFne;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Facture normalisée électronique (FNE, DGI — CdC § 9.4). `lignes`, `payload_fne` et `reponse_fne`
 * sont FIGÉS à la transmission : un changement de paramètre ne touche jamais une facture émise.
 *
 * @property int $id
 * @property string $numero
 * @property TypeDeFacture $type
 * @property int $sejour_id
 * @property int $client_id
 * @property int|null $facture_origine_id
 * @property string|null $motif_avoir
 * @property int $montant_ht
 * @property int $montant_tva
 * @property int $autres_taxes
 * @property int $montant_ttc
 * @property array<int, array<string, mixed>> $lignes
 * @property StatutDeTransmissionFne $statut_transmission
 * @property string|null $reference_dgi
 * @property string|null $token_qr
 * @property string|null $ncc_dgi
 * @property int|null $solde_stickers
 * @property string|null $motif_refus_dgi
 * @property array<string, mixed>|null $payload_fne
 * @property array<string, mixed>|null $reponse_fne
 * @property int|null $transmise_par
 * @property Carbon|null $transmise_le
 * @property int $genere_par
 */
class Facture extends Model
{
    use EstAudite;

    protected $table = 'factures';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TypeDeFacture::class,
            'statut_transmission' => StatutDeTransmissionFne::class,
            'lignes' => 'array',
            'payload_fne' => 'array',
            'reponse_fne' => 'array',
            'transmise_le' => 'datetime',
            'montant_ht' => 'integer', 'montant_tva' => 'integer', 'autres_taxes' => 'integer', 'montant_ttc' => 'integer',
            'solde_stickers' => 'integer',
        ];
    }

    /** @return BelongsTo<Sejour, $this> */
    public function sejour(): BelongsTo
    {
        return $this->belongsTo(Sejour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * La facture d'origine, uniquement pour un avoir.
     *
     * @return BelongsTo<Facture, $this>
     */
    public function factureOrigine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'facture_origine_id');
    }

    /**
     * Les avoirs déjà émis sur cette facture.
     *
     * @return HasMany<Facture, $this>
     */
    public function avoirs(): HasMany
    {
        return $this->hasMany(self::class, 'facture_origine_id');
    }

    /** @return BelongsTo<User, $this> */
    public function transmetteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transmise_par');
    }

    public function estTransmise(): bool
    {
        return $this->statut_transmission === StatutDeTransmissionFne::Transmise;
    }

    public function libelleAudit(): string
    {
        return $this->type->libelle().' '.$this->numero;
    }
}
