<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FamilyRewardSetting extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'points_per_task',
        'allowance_per_task',
    ];

    protected function casts(): array
    {
        return [
            'points_per_task' => 'integer',
            'allowance_per_task' => 'decimal:2',
        ];
    }
}
