<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMedicationLog extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'medication_id',
        'taken_by',
        'taken_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FamilyMedication, $this> */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(FamilyMedication::class, 'medication_id');
    }

    /** @return BelongsTo<User, $this> */
    public function taker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
