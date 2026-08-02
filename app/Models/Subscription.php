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
        'renewal_grace_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewal_grace_ends_at' => 'datetime',
        ];
    }

    public function hasPaidAccess(): bool
    {
        if ($this->status === 'past_due') {
            if ($this->renewal_grace_ends_at === null) {
                return true;
            }

            return now()->lte($this->renewal_grace_ends_at);
        }

        if (! in_array($this->status, ['active', 'cancelled'], true)) {
            return false;
        }

        if ($this->current_period_end === null) {
            return $this->status === 'active';
        }

        // Durante gracia de renovación el acceso lo cubre past_due; active/cancelled
        // solo mientras el periodo no haya vencido.
        return now()->toDateString() <= $this->current_period_end->toDateString();
    }

    public function isPendingCancellation(): bool
    {
        return $this->status === 'cancelled' && $this->hasPaidAccess();
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function graceExpired(): bool
    {
        return $this->renewal_grace_ends_at !== null
            && now()->gte($this->renewal_grace_ends_at);
    }

    public function canAutoRenew(): bool
    {
        if ($this->cancelled_at !== null) {
            return false;
        }

        if (! in_array($this->status, ['active', 'past_due'], true)) {
            return false;
        }

        $hasMirrorCard = $this->renewal_card_last4 !== null;
        $hasMethods = FamilyPaymentMethod::query()
            ->where('family_id', $this->family_id)
            ->where('status', 'active')
            ->exists();

        if (! $hasMirrorCard && ! $hasMethods) {
            return false;
        }

        $renewalUser = $this->relationLoaded('renewalUser')
            ? $this->renewalUser
            : $this->renewalUser()->first();

        if ($renewalUser !== null && ! $renewalUser->isActive()) {
            return false;
        }

        return true;
    }

    public function freeFromDate(): ?\Illuminate\Support\Carbon
    {
        if ($this->status === 'past_due' && $this->renewal_grace_ends_at !== null) {
            return $this->renewal_grace_ends_at->copy();
        }

        if ($this->current_period_end === null) {
            return null;
        }

        return SubscriptionPeriod::freeFromAfter($this->current_period_end);
    }

    public function accessUntilDate(): ?string
    {
        if ($this->status === 'past_due' && $this->renewal_grace_ends_at !== null) {
            return $this->renewal_grace_ends_at->toDateString();
        }

        if ($this->current_period_end === null) {
            return null;
        }

        return SubscriptionPeriod::accessUntilDate($this->current_period_end);
    }

    public function isDueForRenewal(): bool
    {
        if ($this->cancelled_at !== null) {
            return false;
        }

        if ($this->current_period_end === null) {
            return false;
        }

        if (now()->toDateString() <= $this->current_period_end->toDateString()) {
            return false;
        }

        if ($this->status === 'past_due') {
            return ! $this->graceExpired();
        }

        return $this->status === 'active' && $this->canAutoRenew();
    }

    /** Clave de periodo a renovar (evita cobro doble del mismo periodo). */
    public function renewalPeriodKey(): ?string
    {
        if ($this->current_period_end === null) {
            return null;
        }

        return $this->id.'|'.$this->current_period_end->toDateString();
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
