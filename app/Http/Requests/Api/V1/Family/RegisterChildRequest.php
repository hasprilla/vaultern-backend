<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use App\Support\PersonIdentity;
use Illuminate\Foundation\Http\FormRequest;

class RegisterChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $identity = PersonIdentity::rules(documentRequired: true);
        $identity['phone'] = ['nullable', 'string', 'min:7', 'max:32'];

        return array_merge([
            'name'            => ['required', 'string', 'min:2', 'max:120'],
            'guardian_ids'    => ['nullable', 'array'],
            'guardian_ids.*'  => ['integer', 'exists:users,id'],
        ], $identity);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('document_number')) {
            $this->merge([
                'document_number' => PersonIdentity::normalizeDocumentNumber((string) $this->input('document_number')),
            ]);
        }
        if ($this->has('document_type')) {
            $this->merge([
                'document_type' => strtoupper(trim((string) $this->input('document_type'))),
            ]);
        }
    }
}
