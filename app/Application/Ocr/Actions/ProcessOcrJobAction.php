<?php

declare(strict_types=1);

namespace App\Application\Ocr\Actions;

use App\Models\OcrJob;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\Ocr\GoogleVisionOcrService;
use App\Services\Ocr\LlmOcrCopilotService;
use App\Support\FamilyRealtime;

/** @phpstan-type ProcessResult array{ok: true, job: OcrJob}|array{ok: false, status: int, message: string, code?: string} */
final class ProcessOcrJobAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly GoogleVisionOcrService $vision,
        private readonly LlmOcrCopilotService $llmCopilot,
        private readonly ResolveOcrContentAction $resolveContent,
        private readonly AssertOcrQuotaAction $quota,
    ) {}

    /** @return ProcessResult */
    public function execute(User $actor, string $type, ?string $storedPath, ?string $mimeType): array
    {
        $limitCheck = $this->quota->execute($actor);
        if ($limitCheck !== null) {
            return $limitCheck;
        }

        $payload = $this->resolveContent->execute($type, $storedPath, $this->vision, $this->llmCopilot);
        $job = OcrJob::query()->create([
            'family_id' => $actor->family_id,
            'user_id' => $actor->id,
            'type' => $type,
            'status' => 'done',
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'raw_text' => ['text' => $payload['raw_text']],
            'structured_data' => $payload['structured'],
            'confidence' => $payload['confidence'],
        ]);

        $this->afterStore($actor, $type, $job);

        return ['ok' => true, 'job' => $job];
    }

    private function afterStore(User $actor, string $type, OcrJob $job): void
    {
        $label = match ($type) {
            'handwriting' => 'cuaderno escolar',
            'invoice' => 'factura',
            default => 'documento',
        };
        $this->notifications->notifyFamily(
            $actor,
            'ocr_scan',
            'Documento escaneado',
            "{$actor->name} digitalizó un $label con OCR",
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
    }
}
