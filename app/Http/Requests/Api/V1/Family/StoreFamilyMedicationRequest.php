<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use Illuminate\Foundation\Http\FormRequest;

class StoreFamilyMedicationRequest extends FormRequest
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
            'dose_text' => ['nullable', 'string', 'max:120'],
            'schedule_times' => ['nullable', 'array', 'max:12'],
            'schedule_times.*' => ['string', 'max:8'],
            'patient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
