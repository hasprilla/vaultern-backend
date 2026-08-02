<?php

declare(strict_types=1);

namespace App\Application\Shared\Actions;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreAttachmentsAction
{
    public const MAX_FILES = 5;

    public const MAX_KB = 10240;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return list<Attachment>
     */
    public function execute(
        User $actor,
        Model $owner,
        array $files,
        string $folder,
        string $defaultKind = 'image',
        ?int $maxFiles = null,
    ): array {
        $max = $maxFiles ?? self::MAX_FILES;
        $existing = Attachment::query()
            ->where('attachable_type', $owner->getMorphClass())
            ->where('attachable_id', $owner->getKey())
            ->count();

        if ($existing + count($files) > $max) {
            throw ValidationException::withMessages([
                'attachments' => "Máximo {$max} archivos por elemento.",
            ]);
        }

        $familyId = (string) ($owner->getAttribute('family_id') ?? $actor->family_id);
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $mime = $this->resolveMime($file);
            $kind = $this->resolveKind($mime, $defaultKind);
            $path = $file->store(
                "attachments/{$familyId}/{$folder}/{$owner->getKey()}",
                'public',
            );

            $stored[] = Attachment::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $familyId,
                'user_id' => $actor->id,
                'attachable_type' => $owner->getMorphClass(),
                'attachable_id' => $owner->getKey(),
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => (int) $file->getSize(),
                'kind' => $kind,
            ]);
        }

        return $stored;
    }

    private function resolveMime(UploadedFile $file): string
    {
        $detected = (string) ($file->getMimeType() ?: '');
        if ($detected !== '' && $detected !== 'application/octet-stream') {
            return $detected;
        }

        $client = (string) ($file->getClientMimeType() ?: '');
        if ($client !== '' && $client !== 'application/octet-stream') {
            return $client;
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => $detected !== '' ? $detected : 'application/octet-stream',
        };
    }

    private function resolveKind(string $mime, string $defaultKind): string
    {
        if (str_starts_with($mime, 'image/')) {
            return in_array($defaultKind, ['receipt', 'agreement', 'image'], true)
                ? ($defaultKind === 'document' ? 'image' : $defaultKind)
                : 'image';
        }

        if ($mime === 'application/pdf') {
            return in_array($defaultKind, ['receipt', 'agreement', 'document'], true)
                ? $defaultKind
                : 'document';
        }

        return $defaultKind;
    }
}
