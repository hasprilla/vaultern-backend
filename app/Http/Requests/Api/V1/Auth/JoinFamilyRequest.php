<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PersonIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'name'        => ['required', 'string', 'min:2', 'max:120'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8'],
            'invite_code' => ['required', 'string', 'size:8'],
            'invited_by'  => ['nullable', 'integer', 'exists:users,id'],
            'role'        => ['required', 'string', Rule::in(['padre', 'madre'])],
        ], PersonIdentity::rules(documentRequired: true));
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
