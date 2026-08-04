<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Datos de identidad de la persona (no basta solo el correo en plataforma compacta).
 */
final class PersonIdentity
{
    /** @return list<string> */
    public static function documentTypes(): array
    {
        return ['CC', 'TI', 'RC', 'CE', 'PA', 'PPT', 'NIT', 'OTRO'];
    }

    public static function normalizeDocumentNumber(string $value): string
    {
        $normalized = preg_replace('/[\s.\-]/', '', trim($value)) ?? trim($value);

        return strtoupper($normalized);
    }

    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return preg_replace('/\s+/', '', $trimmed) ?? $trimmed;
    }

    /**
     * Reglas de formulario para crear/actualizar persona.
     *
     * @return array<string, mixed>
     */
    public static function rules(bool $documentRequired = true, ?int $ignoreUserId = null): array
    {
        $docNumberRules = [
            $documentRequired ? 'required' : 'nullable',
            'string',
            'min:4',
            'max:64',
            function (string $attribute, mixed $value, \Closure $fail) use ($ignoreUserId): void {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }
                $normalized = self::normalizeDocumentNumber($value);
                $query = User::query()->where('document_number', $normalized);
                if ($ignoreUserId !== null) {
                    $query->where('id', '!=', $ignoreUserId);
                }
                if ($query->exists()) {
                    $fail('Este número de documento ya está registrado.');
                }
            },
        ];

        return [
            'document_type' => [
                $documentRequired ? 'required' : 'nullable',
                'string',
                Rule::in(self::documentTypes()),
            ],
            'document_number' => $docNumberRules,
            'phone' => ['required', 'string', 'min:7', 'max:32'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{document_type: ?string, document_number: ?string, phone: ?string, birthdate: ?string, address: ?string}
     */
    public static function extract(array $input): array
    {
        $docNumber = isset($input['document_number']) && is_string($input['document_number'])
            ? self::normalizeDocumentNumber($input['document_number'])
            : null;

        return [
            'document_type' => isset($input['document_type']) ? strtoupper(trim((string) $input['document_type'])) : null,
            'document_number' => $docNumber === '' ? null : $docNumber,
            'phone' => self::normalizePhone(isset($input['phone']) ? (string) $input['phone'] : null),
            'birthdate' => isset($input['birthdate']) && $input['birthdate'] !== ''
                ? (string) $input['birthdate']
                : null,
            'address' => isset($input['address']) && trim((string) $input['address']) !== ''
                ? trim((string) $input['address'])
                : null,
        ];
    }
}
