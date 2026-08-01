<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use Illuminate\Foundation\Http\FormRequest;

class RegisterChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'min:2', 'max:120'],
            // Otros padres/madres/tutores del núcleo que comparten este hijo.
            'guardian_ids'    => ['nullable', 'array'],
            'guardian_ids.*'  => ['integer', 'exists:users,id'],
        ];
    }
}
