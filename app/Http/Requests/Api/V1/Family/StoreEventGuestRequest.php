<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
