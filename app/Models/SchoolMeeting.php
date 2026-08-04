<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolMeeting extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'school_id',
        'campus_id',
        'school_class_id',
        'school_group_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<SchoolMeetingRsvp> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(SchoolMeetingRsvp::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
