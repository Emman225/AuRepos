<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Une ligne du journal d'audit. Lecture seule : la base refuse UPDATE et DELETE.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $auteur
 * @property string|null $profil
 * @property string $action
 * @property string|null $sujet_type
 * @property int|null $sujet_id
 * @property string|null $sujet_libelle
 * @property array<string, mixed>|null $avant
 * @property array<string, mixed>|null $apres
 * @property string $recit
 * @property string|null $ip
 * @property string|null $adresse
 * @property Carbon $cree_le
 */
class EntreeAudit extends Model
{
    protected $table = 'journal_audit';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['avant' => 'array', 'apres' => 'array', 'cree_le' => 'datetime'];
    }
}
