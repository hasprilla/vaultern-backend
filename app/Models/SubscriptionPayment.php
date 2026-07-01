<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPayment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'subscription_id',
        'user_id',
        'plan_code',
        'billing',
        'amount_cents',
        'currency',
        'status',
        'provider',
        'card_brand',
        'card_last4',
        'card_holder_name',
        'payment_reference',
        'failure_reason',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentEvent::class, 'payment_id');
    }
}
