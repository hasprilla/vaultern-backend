<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSchoolScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slots' => ['sometimes', 'array', 'min:1'],
            'slots.*.day' => ['required'],
            'slots.*.start' => ['required', 'string'],
            'slots.*.end' => ['required', 'string'],
            'slots.*.subject' => ['nullable', 'string', 'max:120'],
            'slots.*.kind' => ['nullable', Rule::in(['lesson', 'break'])],
            'exceptions' => ['sometimes', 'nullable', 'array'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.end_date' => ['nullable', 'date', 'after_or_equal:exceptions.*.date'],
            'exceptions.*.type' => ['required', Rule::in(['vacation', 'no_class'])],
            'exceptions.*.label' => ['nullable', 'string', 'max:120'],
            'campus_id' => ['nullable', 'uuid'],
            'school_class_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
