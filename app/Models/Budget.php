<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'name',
        'amount',
        'currency',
        'period',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'amount'     => 'decimal:2',
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }
}
