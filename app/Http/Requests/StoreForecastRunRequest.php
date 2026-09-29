<?php

namespace App\Http\Requests;

use App\Enums\ForecastType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForecastRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'forecast_type' => ['required', Rule::in(ForecastType::values())],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'simulation_count' => ['sometimes', 'integer', 'min:100', 'max:1000000'],
            'confidence_level' => ['sometimes', 'integer', 'between:50,99'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
