<?php

namespace App\Domain\Comptes\Models;

use App\Domain\Audit\Concerns\EstAudite;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Sejours\Models\Client;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Un compte, quel que soit son profil. Les fiches métier (propriétaire,
 * client, restaurateur…) viendront s'y rattacher ; ce modèle ne porte que
 * l'identité, la connexion et le profil.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $prenoms
 * @property string $email
 * @property string|null $telephone
 * @property string|null $identifiant
 * @property Profil $profil
 * @property StatutCompte $statut
 * @property int|null $agence_id
 * @property int|null $parraine_par_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $derniere_connexion_le
 * @property Carbon|null $mot_de_passe_change_le
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use EstAudite, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'nom', 'prenoms', 'email', 'telephone', 'identifiant',
        'password', 'profil', 'statut', 'agence_id',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'profil' => Profil::class,
            'statut' => StatutCompte::class,
            'email_verified_at' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'mot_de_passe_change_le' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Agence, $this> */
    public function agence(): BelongsTo
    {
        return $this->belongsTo(Agence::class);
    }

    /**
     * Résidences confiées à un gestionnaire : « un gestionnaire ne voit que ses résidences » (CdC § 9.5).
     *
     * @return BelongsToMany<Residence, $this>
     */
    public function residences(): BelongsToMany
    {
        return $this->belongsToMany(Residence::class, 'gestionnaire_residences')->withTimestamps();
    }

    /**
     * Fiche client (TVA, nature, à terme, liste noire) — uniquement pour un profil « client ».
     *
     * @return HasOne<Client, $this>
     */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function nomComplet(): string
    {
        return trim("{$this->prenoms} {$this->nom}");
    }

    /**
     * Saisir un règlement au guichet : un administrateur ou un caissier (gestionnaire), à condition
     * d'être rattaché à une agence (CdC § 8.1). Valider, prouver et finaliser restent réservés aux administrateurs.
     */
    public function peutEncaisser(): bool
    {
        return $this->agence_id !== null
            && ($this->profil->estAdministrateur() || $this->profil === Profil::Gestionnaire);
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Le profil voyage dans le jeton à titre indicatif seulement :
     * toute autorisation relit le compte en base, jamais le jeton.
     *
     * @return array<string, string>
     */
    public function getJWTCustomClaims(): array
    {
        return ['profil' => $this->profil->value];
    }

    public function libelleAudit(): string
    {
        return $this->profil->libelle().' '.$this->nomComplet().' ('.$this->email.')';
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
