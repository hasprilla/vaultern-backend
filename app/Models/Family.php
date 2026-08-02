<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Family extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'plan',
        'invite_code',
        'owner_user_id',
        'timezone',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Family $family) {
            if (empty($family->invite_code)) {
                $family->invite_code = strtoupper(Str::random(8));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null
            && $this->owner_user_id !== null
            && (int) $this->owner_user_id === (int) $user->id;
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(FamilyPaymentMethod::class);
    }

    public function activePlanCode(): string
    {
        return $this->resolveActivePlanCode();
    }

    public function reconcileSubscriptionPlan(): void
    {
        $code = $this->resolveActivePlanCode();

        if (($this->plan ?: 'free') !== $code) {
            $this->update(['plan' => $code]);
        }
    }

    private function resolveActivePlanCode(): string
    {
        $subscription = $this->subscription;

        if ($subscription !== null && $subscription->hasPaidAccess()) {
            return $subscription->plan_code;
        }

        return 'free';
    }
}
