<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ActivityType::values())],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:activity_date'],
            'status' => ['sometimes', Rule::in(ActivityStatus::values())],
        ];
    }
}
