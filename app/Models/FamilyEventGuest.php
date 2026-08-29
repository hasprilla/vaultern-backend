<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyEventGuest extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'user_id',
        'name',
        'email',
        'phone',
        'status',
        'note',
        'invited_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FamilyEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(FamilyEvent::class, 'event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
