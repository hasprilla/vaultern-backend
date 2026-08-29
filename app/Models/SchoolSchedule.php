<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolSchedule extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'school_id',
        'campus_id',
        'school_class_id',
        'title',
        'slots',
        'exceptions',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'exceptions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SchoolScheduleShare> */
    public function shares(): HasMany
    {
        return $this->hasMany(SchoolScheduleShare::class);
    }
}
