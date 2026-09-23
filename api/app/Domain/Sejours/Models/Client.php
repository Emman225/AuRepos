<?php

namespace App\Domain\Sejours\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Fiche du client : sa nature (gabarit de facture), ses bascules de TVA, et son éventuelle
 * ligne de crédit (client à terme — CdC § 5.1, 5.3).
 *
 * @property int $id
 * @property int $user_id
 * @property string $nature
 * @property string|null $raison_sociale
 * @property string|null $ncc
 * @property string|null $rccm
 * @property bool $tva_hebergement
 * @property bool $tva_transfert
 * @property string|null $code_exoneration
 * @property string|null $tva_motif
 * @property int|null $tva_motif_par
 * @property Carbon|null $tva_motif_le
 * @property StatutDemandeATerme $statut_a_terme
 * @property int $plafond_credit
 * @property Carbon|null $demande_a_terme_le
 * @property string|null $a_terme_motif_refus
 * @property int|null $a_terme_traite_par
 * @property Carbon|null $a_terme_traite_le
 * @property bool $liste_noire
 * @property string|null $liste_noire_motif
 * @property int|null $liste_noire_par
 * @property Carbon|null $liste_noire_le
 */
class Client extends Model
{
    use EstAudite;

    public const NATURES = ['b2c' => 'Particulier', 'b2b' => 'Entreprise', 'b2g' => 'Administration', 'b2f' => 'Client étranger'];

    protected $table = 'clients';

    protected $fillable = [
        'user_id', 'nature', 'raison_sociale', 'ncc', 'rccm', 'tva_hebergement', 'tva_transfert', 'code_exoneration',
        'statut_a_terme', 'plafond_credit', 'demande_a_terme_le', 'a_terme_motif_refus', 'a_terme_traite_par', 'a_terme_traite_le',
        'liste_noire', 'liste_noire_motif', 'liste_noire_par', 'liste_noire_le',
        'tva_motif', 'tva_motif_par', 'tva_motif_le',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['nature' => 'b2c', 'tva_hebergement' => true, 'tva_transfert' => true, 'statut_a_terme' => 'aucune', 'plafond_credit' => 0, 'liste_noire' => false];

    protected function casts(): array
    {
        return [
            'tva_hebergement' => 'boolean', 'tva_transfert' => 'boolean',
            'statut_a_terme' => StatutDemandeATerme::class, 'demande_a_terme_le' => 'datetime', 'a_terme_traite_le' => 'datetime',
            'liste_noire' => 'boolean', 'liste_noire_le' => 'datetime', 'tva_motif_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return MorphMany<PieceJustificative, $this> */
    public function pieces(): MorphMany
    {
        return $this->morphMany(PieceJustificative::class, 'titulaire');
    }

    /** Toute organisation doit citer son bon de commande interne à la réservation (CdC § 5.2). */
    public function doitFournirUnBonDeCommande(): bool
    {
        return $this->nature !== 'b2c';
    }

    /** Seul un client à terme ACCEPTÉ peut régler après le séjour (CdC § 5.1). */
    public function estATerme(): bool
    {
        return $this->statut_a_terme === StatutDemandeATerme::Acceptee;
    }

    public static function de(User $utilisateur): self
    {
        return self::firstOrCreate(['user_id' => $utilisateur->id]);
    }

    public function libelleAudit(): string
    {
        return 'fiche client n° '.$this->id;
    }
}
