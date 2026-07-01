<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'plan_code',
        'billing',
        'status',
        'provider',
        'provider_subscription_id',
        'current_period_end',
        'cancelled_at',
        'renewal_card_last4',
        'renewal_card_brand',
        'renewal_card_holder_name',
        'renewal_user_id',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function hasPaidAccess(): bool
    {
        if (! in_array($this->status, ['active', 'cancelled'], true)) {
            return false;
        }

        if ($this->current_period_end === null) {
            return $this->status === 'active';
        }

        return now()->toDateString() <= $this->current_period_end->toDateString();
    }

    public function isPendingCancellation(): bool
    {
        return $this->status === 'cancelled' && $this->hasPaidAccess();
    }

    public function canAutoRenew(): bool
    {
        if ($this->status !== 'active' || $this->cancelled_at !== null) {
            return false;
        }

        if ($this->renewal_card_last4 === null) {
            return false;
        }

        $renewalUser = $this->relationLoaded('renewalUser')
            ? $this->renewalUser
            : $this->renewalUser()->first();

        if ($renewalUser === null || ! $renewalUser->isActive()) {
            return false;
        }

        return true;
    }

    public function freeFromDate(): ?\Illuminate\Support\Carbon
    {
        if ($this->current_period_end === null) {
            return null;
        }

        return SubscriptionPeriod::freeFromAfter($this->current_period_end);
    }

    public function accessUntilDate(): ?string
    {
        if ($this->current_period_end === null) {
            return null;
        }

        return SubscriptionPeriod::accessUntilDate($this->current_period_end);
    }

    public function isDueForRenewal(): bool
    {
        return $this->canAutoRenew()
            && $this->current_period_end !== null
            && now()->toDateString() > $this->current_period_end->toDateString();
    }

    public function renewalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewal_user_id');
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
