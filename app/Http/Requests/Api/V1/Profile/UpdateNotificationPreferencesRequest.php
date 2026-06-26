<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'push_enabled' => ['sometimes', 'boolean'],
            'tasks'        => ['sometimes', 'boolean'],
            'finance'      => ['sometimes', 'boolean'],
            'family'       => ['sometimes', 'boolean'],
            'reminders'    => ['sometimes', 'boolean'],
        ];
    }
}
