<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => ['required', 'string', 'email', 'max:255'],
            'device_id'  => ['nullable', 'string', 'max:255'],
            'platform'   => ['nullable', 'string', 'max:32'],
            'fcm_token'  => ['nullable', 'string', 'max:512'],
        ];
    }
}
