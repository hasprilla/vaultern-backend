<?php

declare(strict_types=1);

namespace App\Application\Ocr\Actions;

use App\Services\Ocr\GoogleVisionOcrService;
use App\Services\Ocr\LlmOcrCopilotService;

/**
 * Extrae texto real vía Vision/LLM. Sin motor configurado no inventa datos demo.
 */
final class ResolveOcrContentAction
{
    /**
     * @return array{raw_text: string, structured: array<string, mixed>, confidence: float}
     */
    public function execute(
        string $type,
        ?string $storedPath,
        GoogleVisionOcrService $vision,
        LlmOcrCopilotService $llmCopilot,
    ): array {
        $rawText = $storedPath === null
            ? 'Escaneo sin imagen adjunta.'
            : 'Pendiente de motor OCR. Configura Google Vision o el copiloto LLM.';
        $confidence = 0.0;
        $structured = [
            'engine' => 'none',
            'tasks' => [],
            'lines' => [],
        ];

        if ($storedPath !== null && $vision->isConfigured()) {
            $result = $vision->extractText($storedPath);
            if ($result !== null) {
                $rawText = $result['text'];
                $confidence = (float) $result['confidence'];
                $structured = $vision->structure($type, $rawText);
                $structured['engine'] = 'google_vision';
            }
        }

        $enriched = $llmCopilot->enrich($type, $rawText);
        if (is_array($enriched) && $enriched !== []) {
            $structured = array_merge($structured, $enriched);
            $structured['engine'] = filled(config('services.gemini.api_key')) ? 'gemini' : 'openai';
            $confidence = min(0.98, max($confidence, 0.9));
        }

        return [
            'raw_text' => $rawText,
            'structured' => $structured,
            'confidence' => $confidence,
        ];
    }
}
