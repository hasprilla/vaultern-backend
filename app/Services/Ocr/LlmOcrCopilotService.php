<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Enriquece el texto OCR con Gemini o OpenAI (opcional).
 * Sin API key: no hace nada (se mantiene el parsing regex de Vision).
 */
final class LlmOcrCopilotService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'))
            || filled(config('services.openai.api_key'));
    }

    /**
     * @return array<string, mixed>|null structured_data enriquecido
     */
    public function enrich(string $type, string $rawText): ?array
    {
        $rawText = trim($rawText);
        if ($rawText === '' || ! $this->isConfigured()) {
            return null;
        }

        $prompt = $this->promptFor($type, mb_substr($rawText, 0, 6000));

        try {
            if (filled(config('services.gemini.api_key'))) {
                return $this->callGemini($prompt);
            }

            return $this->callOpenAi($prompt);
        } catch (\Throwable $e) {
            Log::warning('ocr.llm_copilot_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function promptFor(string $type, string $text): string
    {
        $schema = match ($type) {
            'invoice' => '{"vendor":"string|null","total":number|null,"currency":"COP|USD|null","invoice_date":"YYYY-MM-DD|null","raw_excerpt":"string","suggested_actions":[{"type":"create_expense","label":"string"}]}',
            'handwriting' => '{"tasks":[{"title":"string","subject":"string|null","due_date":"YYYY-MM-DD|null"}],"raw_excerpt":"string","suggested_actions":[{"type":"create_task","label":"string"}]}',
            default => '{"lines":["string"],"tasks":[{"title":"string","subject":"string|null","due_date":"YYYY-MM-DD|null"}],"raw_excerpt":"string","suggested_actions":[{"type":"create_task","label":"string"}]}',
        };

        return <<<PROMPT
Eres el copiloto de Zumifly (app familiar en Colombia). Extrae datos del texto OCR.
Responde SOLO JSON válido, sin markdown, con este esquema:
{$schema}

Texto OCR:
---
{$text}
---
PROMPT;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callGemini(string $prompt): ?array
    {
        $key = (string) config('services.gemini.api_key');
        $model = (string) config('services.gemini.model', 'gemini-2.0-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";

        $res = Http::timeout(40)->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        if (! $res->successful()) {
            return null;
        }

        $text = data_get($res->json(), 'candidates.0.content.parts.0.text');
        if (! is_string($text) || $text === '') {
            return null;
        }

        return $this->decodeJson($text);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callOpenAi(string $prompt): ?array
    {
        $key = (string) config('services.openai.api_key');
        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        $res = Http::withToken($key)
            ->timeout(40)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Respondes solo JSON válido.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $res->successful()) {
            return null;
        }

        $text = data_get($res->json(), 'choices.0.message.content');
        if (! is_string($text) || $text === '') {
            return null;
        }

        return $this->decodeJson($text);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $text): ?array
    {
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
