<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentFileController extends Controller
{
    public function show(Request $request, string $attachment): StreamedResponse
    {
        $user = $request->user();
        $model = Attachment::query()->findOrFail($attachment);

        if ($user === null
            || $user->family_id === null
            || (string) $user->family_id !== (string) $model->family_id
            || ! $user->hasActiveFamilyMembership()) {
            abort(403, 'No autorizado para ver este archivo.');
        }

        $disk = $model->disk ?: 'public';
        $path = (string) $model->path;

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        $mime = $model->mime_type ?: Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        $name = $model->original_name ?: basename($path);

        return Storage::disk($disk)->response(
            $path,
            $name,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }
}
