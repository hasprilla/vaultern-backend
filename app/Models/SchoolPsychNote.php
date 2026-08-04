<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPsychNote extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'school_psych_case_id',
        'created_by',
        'body',
        'notify_guardians',
    ];

    protected function casts(): array
    {
        return [
            'notify_guardians' => 'boolean',
        ];
    }

    /** @return BelongsTo<SchoolPsychCase, $this> */
    public function psychCase(): BelongsTo
    {
        return $this->belongsTo(SchoolPsychCase::class, 'school_psych_case_id');
    }
}
