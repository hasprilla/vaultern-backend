<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

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
        return [
            'name'        => ['required', 'string', 'min:2', 'max:120'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'    => ['required', 'string', 'min:8'],
            'invite_code' => ['required', 'string', 'size:8'],
            // Opcional: si no viene, el backend elige un padre/madre de la familia.
            'invited_by'  => ['nullable', 'integer', 'exists:users,id'],
            'role'        => ['required', 'string', Rule::in(['padre', 'madre'])],
        ];
    }
}
