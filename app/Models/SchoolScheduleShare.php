<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolScheduleShare extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'school_schedule_id',
        'school_group_id',
        'user_id',
        'permission',
    ];

    /** @return BelongsTo<SchoolSchedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SchoolSchedule::class, 'school_schedule_id');
    }
}
