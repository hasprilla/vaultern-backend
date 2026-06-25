<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:120'],
            'email'    => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::query()->where('email', $value)->first();

                    if ($user !== null && $user->email_verified_at !== null) {
                        $fail('Este email ya está registrado. Inicia sesión.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', 'string', Rule::in(['padre', 'madre'])],
        ];
    }
}
