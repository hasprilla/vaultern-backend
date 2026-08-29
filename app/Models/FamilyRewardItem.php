<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FamilyRewardItem extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'title',
        'cost_points',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'cost_points' => 'integer',
            'active' => 'boolean',
        ];
    }
}
