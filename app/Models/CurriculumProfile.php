<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumProfile extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'country_code',
        'level',
        'shift',
        'label',
        'weekly_hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekly_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<CurriculumBlock> */
    public function blocks(): HasMany
    {
        return $this->hasMany(CurriculumBlock::class)->orderBy('sort_order');
    }
}
