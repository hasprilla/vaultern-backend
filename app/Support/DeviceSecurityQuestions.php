<?php

declare(strict_types=1);

namespace App\Support;

/** Preguntas de seguridad predefinidas para recuperación de dispositivo. */
final class DeviceSecurityQuestions
{
    /** @var array<string, string> */
    public const OPTIONS = [
        'pet_name' => '¿Cuál era el nombre de tu primera mascota?',
        'school' => '¿Cómo se llamaba tu escuela primaria?',
        'city' => '¿En qué ciudad naciste?',
        'mother_maiden' => '¿Cuál es el apellido de soltera de tu madre?',
        'first_car' => '¿Cuál fue la marca o modelo de tu primer vehículo?',
    ];

    public static function isValidKey(string $key): bool
    {
        return array_key_exists($key, self::OPTIONS);
    }

    public static function label(string $key): ?string
    {
        return self::OPTIONS[$key] ?? null;
    }

    /** @return list<array{key: string, question: string}> */
    public static function list(): array
    {
        $out = [];
        foreach (self::OPTIONS as $key => $question) {
            $out[] = ['key' => $key, 'question' => $question];
        }

        return $out;
    }

    public static function normalizeAnswer(string $answer): string
    {
        $normalized = mb_strtolower(trim($answer));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return $normalized;
    }
}
