<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForecastScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'win_rate_adjustment' => ['sometimes', 'numeric', 'between:-100,100'],
            'revenue_adjustment' => ['sometimes', 'numeric', 'min:0'],
            'opportunity_adjustment' => ['sometimes', 'numeric', 'between:-100,100'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
