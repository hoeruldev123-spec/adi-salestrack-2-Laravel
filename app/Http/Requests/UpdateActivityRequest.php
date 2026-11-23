<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', Rule::in(ActivityType::values())],
            'activity_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'activity_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:activity_date'],
            'status' => ['sometimes', Rule::in(ActivityStatus::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('type') && $this->has('activity_type')) {
            $this->merge(['type' => $this->input('activity_type')]);
        }

        if (! $this->has('activity_type') && $this->has('type')) {
            $this->merge(['activity_type' => $this->input('type')]);
        }
    }
}
