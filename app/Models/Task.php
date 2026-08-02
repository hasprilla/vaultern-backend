<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Task extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'created_by',
        'assigned_to',
        'source_broadcast_id',
        'school_id',
        'created_by_role',
        'title',
        'description',
        'status',
        'priority',
        'is_school',
        'subject',
        'due_date',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_school'    => 'boolean',
            'due_date'     => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(SchoolTaskBroadcast::class, 'source_broadcast_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('created_at');
    }
}
