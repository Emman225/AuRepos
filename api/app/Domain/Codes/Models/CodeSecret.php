<?php

namespace App\Domain\Codes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sujet_type
 * @property int $sujet_id
 * @property string $usage
 * @property string $code chiffré en base ; en clair seulement en mémoire
 * @property int $tentatives
 * @property Carbon|null $verrouille_le
 * @property Carbon|null $utilise_le
 * @property Carbon|null $dernier_envoi_le
 */
class CodeSecret extends Model
{
    protected $table = 'codes_secrets';

    protected $guarded = [];

    // Ceinture et bretelles : même sérialisé par mégarde, le modèle ne livre pas son code.
    protected $hidden = ['code'];

    protected function casts(): array
    {
        return ['code' => 'encrypted', 'verrouille_le' => 'datetime', 'utilise_le' => 'datetime', 'dernier_envoi_le' => 'datetime'];
    }
}
