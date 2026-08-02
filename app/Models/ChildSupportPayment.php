<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChildSupportPayment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'agreement_id',
        'family_id',
        'child_id',
        'paid_by',
        'transaction_id',
        'amount',
        'currency',
        'period_month',
        'paid_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_month' => 'date',
            'paid_on' => 'date',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ChildSupportAgreement::class, 'agreement_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('created_at');
    }
}
