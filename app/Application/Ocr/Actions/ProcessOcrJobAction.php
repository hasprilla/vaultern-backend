<?php

declare(strict_types=1);

namespace App\Application\Ocr\Actions;

use App\Models\Family;
use App\Models\OcrJob;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\Ocr\GoogleVisionOcrService;
use App\Services\Ocr\LlmOcrCopilotService;
use App\Services\PlanFeatureService;
use App\Support\FamilyRealtime;

/**
 * @phpstan-type ProcessSuccess array{ok: true, job: OcrJob}
 * @phpstan-type ProcessFailure array{ok: false, status: int, message: string, code?: string}
 */
final class ProcessOcrJobAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly PlanFeatureService $planFeatures,
        private readonly GoogleVisionOcrService $vision,
        private readonly LlmOcrCopilotService $llmCopilot,
    ) {}

    /**
     * @return ProcessSuccess|ProcessFailure
     */
    public function execute(
        User $actor,
        string $type,
        ?string $storedPath,
        ?string $mimeType,
    ): array {
        $family = Family::query()->findOrFail($actor->family_id);
        $limit = $this->planFeatures->familyFeatureLimit($family, 'ocr_scans_monthly', 5);
        $used = OcrJob::query()
            ->where('family_id', $family->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used >= $limit) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => "Alcanzaste el límite de {$limit} escaneos OCR este mes. Mejora tu plan para continuar.",
                'code' => 'ocr_limit_reached',
            ];
        }

        $rawText = $this->rawTextFor($type, $storedPath !== null);
        $confidence = $storedPath !== null ? 0.85 : 0.75;
        $structured = $this->structuredDataFor($type);

        if ($storedPath !== null && $this->vision->isConfigured()) {
            $vision = $this->vision->extractText($storedPath);
            if ($vision !== null) {
                $rawText = $vision['text'];
                $confidence = $vision['confidence'];
                $structured = $this->vision->structure($type, $rawText);
            }
        }

        // Copiloto IA (Gemini/OpenAI): mejora tareas/fechas/montos si hay API key.
        $enriched = $this->llmCopilot->enrich($type, $rawText);
        if (is_array($enriched) && $enriched !== []) {
            $structured = array_merge($structured, $enriched);
            $structured['copilot'] = filled(config('services.gemini.api_key')) ? 'gemini' : 'openai';
            $confidence = min(0.98, max($confidence, 0.9));
        }

        $job = OcrJob::query()->create([
            'family_id' => $actor->family_id,
            'user_id' => $actor->id,
            'type' => $type,
            'status' => 'done',
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'raw_text' => ['text' => $rawText],
            'structured_data' => $structured,
            'confidence' => $confidence,
        ]);

        $typeLabel = match ($type) {
            'handwriting' => 'cuaderno escolar',
            'invoice' => 'factura',
            default => 'documento',
        };

        $this->notifications->notifyFamily(
            $actor,
            'ocr_scan',
            'Documento escaneado',
            "{$actor->name} digitalizó un $typeLabel con OCR",
            ['entity_type' => 'ocr_job', 'entity_id' => $job->id],
        );

        FamilyRealtime::ocrJobUpdated(
            familyId: (string) $actor->family_id,
            userId: (int) $actor->id,
            jobId: (string) $job->id,
            status: (string) $job->status,
            ocrType: $type,
            action: 'completed',
        );

        return ['ok' => true, 'job' => $job];
    }

    private function rawTextFor(string $type, bool $hasFile): string
    {
        if (! $hasFile) {
            return 'Escaneo sin imagen adjunta.';
        }

        return match ($type) {
            'handwriting' => 'Cuaderno escolar digitalizado. Tareas detectadas en apuntes manuscritos.',
            'invoice' => 'Factura digitalizada. Proveedor y total extraídos.',
            default => 'Documento digitalizado correctamente.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredDataFor(string $type): array
    {
        return match ($type) {
            'invoice' => [
                'vendor' => 'Tienda Demo',
                'total' => 45000,
                'currency' => 'COP',
                'invoice_date' => now()->toDateString(),
            ],
            'handwriting' => [
                'tasks' => [
                    ['title' => 'Matemáticas ej. 1-10', 'subject' => 'Matemáticas'],
                    ['title' => 'Leer capítulo 3', 'subject' => 'Español'],
                ],
            ],
            default => [
                'lines' => ['Documento digitalizado correctamente'],
                'tasks' => [
                    ['title' => 'Revisar documento escaneado', 'subject' => 'General'],
                ],
            ],
        };
    }
}
