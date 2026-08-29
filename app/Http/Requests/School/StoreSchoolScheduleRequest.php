<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSchoolScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $schoolId = $this->route('school')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.day' => ['required'],
            'slots.*.start' => ['required', 'string'],
            'slots.*.end' => ['required', 'string'],
            'slots.*.subject' => ['nullable', 'string', 'max:120'],
            'slots.*.kind' => ['nullable', Rule::in(['lesson', 'break'])],
            'exceptions' => ['nullable', 'array'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.end_date' => ['nullable', 'date', 'after_or_equal:exceptions.*.date'],
            'exceptions.*.type' => ['required', Rule::in(['vacation', 'no_class'])],
            'exceptions.*.label' => ['nullable', 'string', 'max:120'],
            'campus_id' => [
                'nullable', 'uuid',
                Rule::exists('school_campuses', 'id')->where('school_id', $schoolId),
            ],
            'school_class_id' => [
                'nullable', 'uuid',
                Rule::exists('school_classes', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
