<?php

namespace App\Domain\Comptes\Models;

use App\Domain\Audit\Concerns\EstAudite;
use Database\Factories\AgenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Guichet d'encaissement. Un caissier encaisse toujours pour SON agence :
 * elle n'est jamais choisie à l'écran, et un compte sans agence ne peut pas
 * encaisser (CdC § 8.1).
 *
 * @property int $id
 * @property string $nom
 * @property bool $active
 */
class Agence extends Model
{
    /** @use HasFactory<AgenceFactory> */
    use EstAudite, HasFactory;

    protected $table = 'agences';

    protected $fillable = ['nom', 'adresse', 'telephone', 'active'];

    /** @var array<string, mixed> */
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return HasMany<User, $this> */
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function libelleAudit(): string
    {
        return 'agence « '.$this->nom.' »';
    }

    protected static function newFactory(): AgenceFactory
    {
        return AgenceFactory::new();
    }
}
