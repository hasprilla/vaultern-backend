<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'family_id',
        'user_id',
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'kind',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function isImage(): bool
    {
        $mime = (string) $this->mime_type;
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if ($mime === 'application/pdf') {
            return false;
        }

        if (in_array((string) $this->kind, ['image', 'receipt'], true)) {
            return true;
        }

        $ext = strtolower((string) pathinfo((string) $this->original_name, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    public function url(?Request $request = null): string
    {
        $path = ltrim((string) $this->path, '/');
        if ($request !== null) {
            return $request->getSchemeAndHttpHost().'/storage/'.$path;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.$path;
    }

    public function deleteFile(): void
    {
        if ($this->path !== '' && Storage::disk($this->disk ?: 'public')->exists($this->path)) {
            Storage::disk($this->disk ?: 'public')->delete($this->path);
        }
    }
}
