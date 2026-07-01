<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'min:2', 'max:120'],
            'email'      => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = \App\Models\User::query()->where('email', $value)->first();

                    if ($user !== null && $user->email_verified_at !== null) {
                        $fail('Este email ya está registrado. Inicia sesión.');
                    }
                },
            ],
            'password'   => ['required', 'string', 'min:8'],
            'role'       => ['required', 'string', \Illuminate\Validation\Rule::in(['padre', 'madre'])],
            'device_id'  => ['nullable', 'string', 'max:255'],
            'platform'   => ['nullable', 'string', 'max:32'],
            'fcm_token'  => ['nullable', 'string', 'max:512'],
        ];
    }
}
