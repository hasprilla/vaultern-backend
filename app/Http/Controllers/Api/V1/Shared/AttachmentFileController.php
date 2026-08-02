<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentFileController extends Controller
{
    public function show(Request $request, string $attachment): BinaryFileResponse|StreamedResponse
    {
        $user = $request->user();
        $model = Attachment::query()->findOrFail($attachment);

        if ($user === null
            || $user->family_id === null
            || (string) $user->family_id !== (string) $model->family_id) {
            abort(403, 'No autorizado para ver este archivo.');
        }

        $path = ltrim((string) $model->path, '/');
        $mime = $model->mime_type ?: 'application/octet-stream';
        $name = $model->original_name ?: basename($path);

        $absolute = $this->resolveAbsolutePath($model->disk ?: 'public', $path);
        if ($absolute === null) {
            abort(404, 'Archivo no encontrado en el servidor.');
        }

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveAbsolutePath(string $disk, string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        foreach (array_unique([$disk, 'public', 'local']) as $candidateDisk) {
            try {
                if (Storage::disk($candidateDisk)->exists($path)) {
                    return Storage::disk($candidateDisk)->path($path);
                }
            } catch (\Throwable) {
                // probar siguiente
            }
        }

        $fallbacks = [
            storage_path('app/public/'.$path),
            storage_path('app/private/'.$path),
            storage_path('app/'.$path),
            public_path('storage/'.$path),
        ];

        foreach ($fallbacks as $full) {
            if (is_file($full) && is_readable($full)) {
                return $full;
            }
        }

        return null;
    }
}
