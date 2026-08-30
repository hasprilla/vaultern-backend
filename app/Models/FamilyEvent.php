<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'status',
        'kind',
        'child_user_id',
        'budget_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'budget_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    /** @return HasMany<FamilyEventGuest> */
    public function guests(): HasMany
    {
        return $this->hasMany(FamilyEventGuest::class, 'event_id');
    }

    /** @return HasMany<FamilyEventExpense> */
    public function expenses(): HasMany
    {
        return $this->hasMany(FamilyEventExpense::class, 'event_id');
    }
}
