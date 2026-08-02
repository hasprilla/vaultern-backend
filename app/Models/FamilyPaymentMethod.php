<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CardMask;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyPaymentMethod extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'user_id',
        'provider',
        'provider_payment_source_id',
        'brand',
        'last4',
        'holder_name',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isChargeable(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->provider === 'simulated') {
            return filled($this->last4);
        }

        return filled($this->provider_payment_source_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'holder_name' => $this->holder_name,
            'masked' => CardMask::display($this->brand, $this->last4),
            'is_default' => (bool) $this->is_default,
            'chargeable' => $this->isChargeable(),
            'status' => $this->status,
        ];
    }
}
