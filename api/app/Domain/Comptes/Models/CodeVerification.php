<?php

namespace App\Domain\Comptes\Models;

use App\Domain\Comptes\Enums\UsageDuCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property UsageDuCode $usage
 * @property string $code_hash
 * @property Carbon $expire_le
 * @property int $tentatives
 * @property Carbon|null $utilise_le
 */
class CodeVerification extends Model
{
    protected $table = 'codes_verification';

    protected $fillable = ['user_id', 'usage', 'code_hash', 'expire_le', 'tentatives', 'utilise_le'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'usage' => UsageDuCode::class,
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
        ];
    }
}
