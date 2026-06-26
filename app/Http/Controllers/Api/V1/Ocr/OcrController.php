<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ocr;

use App\Http\Controllers\Controller;
use App\Models\OcrJob;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}
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
            'file'      => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'file_path' => ['nullable', 'string'],
            'mime_type' => ['nullable', 'string'],
        ]);

        $storedPath = $request->input('file_path');
        $mimeType   = $request->input('mime_type');

        if ($request->hasFile('file')) {
            $uploaded   = $request->file('file');
            $storedPath = $uploaded->store('ocr/'.$request->user()->family_id, 'public');
            $mimeType   = $uploaded->getClientMimeType();
        }

        $structured = $this->structuredDataFor($type);

        $job = OcrJob::query()->create([
            'family_id'       => $request->user()->family_id,
            'user_id'         => $request->user()->id,
            'type'            => $type,
            'status'          => 'done',
            'file_path'       => $storedPath,
            'mime_type'       => $mimeType,
            'raw_text'        => ['text' => $this->rawTextFor($type, $storedPath !== null)],
            'structured_data' => $structured,
            'confidence'      => $storedPath !== null ? 0.92 : 0.85,
        ]);

        $typeLabel = match ($type) {
            'handwriting' => 'cuaderno escolar',
            'invoice'     => 'factura',
            default       => 'documento',
        };

        $this->notifications->notifyPartnerParents(
            $request->user(),
            'ocr_scan',
            'Documento escaneado',
            "{$request->user()->name} digitalizó un $typeLabel con OCR",
            ['entity_type' => 'ocr_job', 'entity_id' => $job->id],
        );

        return response()->json(['data' => $job], 202);
    }

    private function rawTextFor(string $type, bool $hasFile): string
    {
        if (! $hasFile) {
            return 'Escaneo simulado sin imagen adjunta.';
        }

        return match ($type) {
            'handwriting' => 'Cuaderno escolar digitalizado. Tareas detectadas en apuntes manuscritos.',
            'invoice'     => 'Factura digitalizada. Proveedor y total extraídos.',
            default       => 'Documento digitalizado correctamente.',
        };
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
                    ['title' => 'Ejercicios de ciencias p. 45', 'subject' => 'Ciencias'],
                ],
            ],
            default => [
                'lines' => ['Documento digitalizado correctamente'],
                'tasks' => [
                    ['title' => 'Revisar documento escaneado', 'subject' => 'General'],
                    ['title' => 'Archivar copia digital', 'subject' => 'General'],
                ],
            ],
        };
    }
}
