<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use Illuminate\Foundation\Http\FormRequest;

class SyncEventGuestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guests' => ['required', 'array'],
            'guests.*.name' => ['required', 'string', 'max:120'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
            'guests.*.phone' => ['nullable', 'string', 'max:40'],
            'guests.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guests.*.guest_kind' => ['nullable', 'in:adult,child'],
        ];
    }
}
