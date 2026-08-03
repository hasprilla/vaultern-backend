<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChildRewardEvent extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'child_user_id',
        'source_type',
        'source_id',
        'points_delta',
        'allowance_delta',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'points_delta' => 'integer',
            'allowance_delta' => 'decimal:2',
        ];
    }
}
