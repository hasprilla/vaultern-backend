<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildSupportAdjustment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'agreement_id',
        'recorded_by',
        'increase_pct',
        'amount_after',
        'effective_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'increase_pct' => 'decimal:4',
            'amount_after' => 'decimal:2',
            'effective_on' => 'date',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ChildSupportAgreement::class, 'agreement_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
