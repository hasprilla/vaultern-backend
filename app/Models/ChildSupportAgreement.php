<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChildSupportAgreement extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'child_id',
        'payer_user_id',
        'beneficiary_user_id',
        'created_by',
        'initial_amount',
        'currency',
        'default_annual_increase_pct',
        'starts_on',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
            'default_annual_increase_pct' => 'decimal:4',
            'starts_on' => 'date',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ChildSupportAdjustment::class, 'agreement_id')->orderByDesc('effective_on');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ChildSupportPayment::class, 'agreement_id')->orderByDesc('period_month');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('created_at');
    }

    public function currentAmount(): float
    {
        $amount = (float) $this->initial_amount;
        $adjustments = $this->relationLoaded('adjustments')
            ? $this->adjustments
            : $this->adjustments()->orderBy('effective_on')->get();

        foreach ($adjustments->sortBy('effective_on') as $adjustment) {
            $amount = (float) $adjustment->amount_after;
        }

        return round($amount, 2);
    }
}
