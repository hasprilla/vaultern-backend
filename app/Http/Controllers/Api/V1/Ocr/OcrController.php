<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ocr;

use App\Http\Controllers\Controller;
use App\Models\OcrJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OcrController extends Controller
{
    public function processNotebook(Request $request): JsonResponse
    {
        return $this->process($request, 'handwriting');
    }

    public function processDocument(Request $request): JsonResponse
    {
        return $this->process($request, 'document');
    }

    public function processInvoice(Request $request): JsonResponse
    {
        return $this->process($request, 'invoice');
    }

    public function show(string $document): JsonResponse
    {
        $job = OcrJob::query()->findOrFail($document);

        return response()->json(['data' => $job]);
    }

    private function process(Request $request, string $type): JsonResponse
    {
        $request->validate([
            'file_path' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string'],
        ]);

        $job = OcrJob::query()->create([
            'id'              => (string) Str::uuid(),
            'family_id'       => $request->user()->family_id,
            'user_id'         => $request->user()->id,
            'type'            => $type,
            'status'          => 'done',
            'file_path'       => $request->input('file_path'),
            'mime_type'       => $request->input('mime_type'),
            'raw_text'        => ['text' => 'Contenido OCR simulado para desarrollo'],
            'structured_data' => $this->structuredDataFor($type),
            'confidence'      => 0.92,
        ]);

        return response()->json(['data' => $job], 202);
    }

    private function structuredDataFor(string $type): array
    {
        return match ($type) {
            'invoice' => [
                'vendor'       => 'Tienda Demo',
                'total'        => 45000,
                'currency'     => 'COP',
                'invoice_date' => now()->toDateString(),
            ],
            'handwriting' => [
                'tasks' => [
                    ['title' => 'Matemáticas ej. 1-10', 'subject' => 'Matemáticas'],
                    ['title' => 'Leer capítulo 3', 'subject' => 'Español'],
                ],
            ],
            default => ['lines' => ['Documento procesado']],
        };
    }
}
