<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChildRewardBalance extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'child_user_id',
        'points',
        'allowance_balance',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'allowance_balance' => 'decimal:2',
        ];
    }
}
