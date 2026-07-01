<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'   => ['sometimes', 'string', 'min:2', 'max:120'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
