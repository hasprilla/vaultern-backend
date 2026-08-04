<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAnnouncement extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'school_id',
        'campus_id',
        'school_class_id',
        'school_group_id',
        'target_user_id',
        'created_by',
        'type',
        'title',
        'body',
        'data',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
