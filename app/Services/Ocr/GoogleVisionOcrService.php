<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GoogleVisionOcrService
{
    public function isConfigured(): bool
    {
        $key = config('services.google_vision.api_key');

        return is_string($key) && $key !== '';
    }

    /**
     * @return array{text: string, confidence: float}|null
     */
    public function extractText(string $storagePath): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $contents = Storage::disk('public')->get($storagePath);
        if ($contents === null) {
            return null;
        }

        $response = Http::post(
            'https://vision.googleapis.com/v1/images:annotate?key='.config('services.google_vision.api_key'),
            [
                'requests' => [[
                    'image' => ['content' => base64_encode($contents)],
                    'features' => [['type' => 'TEXT_DETECTION']],
                ]],
            ],
        );

        if (! $response->successful()) {
            return null;
        }

        $annotation = $response->json('responses.0.fullTextAnnotation');
        if (! is_array($annotation)) {
            return null;
        }

        $text = trim((string) ($annotation['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        $confidence = 0.9;
        $pages = $annotation['pages'] ?? [];
        if (is_array($pages) && isset($pages[0]['confidence'])) {
            $confidence = (float) $pages[0]['confidence'];
        }

        return ['text' => $text, 'confidence' => $confidence];
    }

    /** @return array<string, mixed> */
    public function structure(string $type, string $text): array
    {
        return match ($type) {
            'invoice' => $this->structureInvoice($text),
            'handwriting' => $this->structureHandwriting($text),
            default => $this->structureDocument($text),
        };
    }

    /** @return array<string, mixed> */
    private function structureInvoice(string $text): array
    {
        preg_match('/(?:total|valor)[:\s]*\$?\s*([\d.,]+)/iu', $text, $totalMatch);
        preg_match('/^([^\n]{3,60})$/m', $text, $vendorMatch);

        return [
            'vendor'       => trim($vendorMatch[1] ?? 'Proveedor detectado'),
            'total'        => isset($totalMatch[1]) ? (float) str_replace([',', '.'], ['', '.'], $totalMatch[1]) : null,
            'currency'     => 'COP',
            'invoice_date' => now()->toDateString(),
            'raw_excerpt'  => mb_substr($text, 0, 500),
        ];
    }

    /** @return array<string, mixed> */
    private function structureHandwriting(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
        $tasks = [];
        foreach (array_slice($lines, 0, 8) as $line) {
            if (mb_strlen($line) < 4) {
                continue;
            }
            $tasks[] = ['title' => $line, 'subject' => 'General'];
        }

        return [
            'tasks' => $tasks !== [] ? $tasks : [
                ['title' => 'Revisar apuntes escaneados', 'subject' => 'General'],
            ],
            'raw_excerpt' => mb_substr($text, 0, 500),
        ];
    }

    /** @return array<string, mixed> */
    private function structureDocument(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));

        return [
            'lines' => array_slice($lines, 0, 12),
            'tasks' => [
                ['title' => 'Revisar documento escaneado', 'subject' => 'General'],
            ],
            'raw_excerpt' => mb_substr($text, 0, 500),
        ];
    }
}
