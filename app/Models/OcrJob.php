<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrJob extends Model
{
    use BelongsToFamily;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'user_id',
        'type',
        'status',
        'file_path',
        'mime_type',
        'raw_text',
        'structured_data',
        'confidence',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'raw_text'        => 'array',
            'structured_data' => 'array',
            'confidence'      => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
