<?php

namespace App\Http\Requests;

use App\Enums\ForecastType;
use App\Enums\ProductType;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
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
            'name' => ['required_without:forecast_name', 'nullable', 'string', 'max:255'],
            'forecast_name' => ['required_without:name', 'nullable', 'string', 'max:150'],
            'forecast_type' => ['sometimes', 'nullable', Rule::in(ForecastType::values())],
            'forecast_scope' => ['sometimes', 'nullable', Rule::in(['OPPORTUNITY', 'ALL'])],
            'historical_start_date' => ['nullable', 'date'],
            'historical_end_date' => ['nullable', 'date'],
            'forecast_start_date' => ['required_without_all:period_start,start_period', 'nullable', 'date'],
            'forecast_end_date' => ['required_without_all:period_end,end_period', 'nullable', 'date'],
            'period_start' => ['required_without_all:forecast_start_date,start_period', 'nullable', 'date'],
            'period_end' => ['required_without_all:forecast_end_date,end_period', 'nullable', 'date'],
            'start_period' => ['required_without_all:forecast_start_date,period_start', 'nullable', 'date'],
            'end_period' => ['required_without_all:forecast_end_date,period_end', 'nullable', 'date'],
            'simulation_count' => ['sometimes', 'integer', 'min:100', 'max:1000000'],
            'confidence_level' => ['sometimes', 'integer', 'between:50,99'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'actual_revenue' => ['nullable', 'numeric', 'min:0'],
            'revenue_category' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(array_merge(['ALL'], array_values(array_filter(
                    ProductType::values(),
                    fn ($type) => $type !== ProductType::PRODUCT->value
                )))),
            ],
            'principal_id' => ['nullable', 'integer', 'exists:principals,id'],
            'recommendation_thresholds' => ['sometimes', 'nullable', 'array'],
            'recommendation_thresholds.high_value_threshold' => ['sometimes', 'numeric', 'min:0'],
            'recommendation_thresholds.low_value_threshold' => ['sometimes', 'numeric', 'min:0'],
            'recommendation_thresholds.high_probability_threshold' => ['sometimes', 'numeric', 'between:0,100'],
            'recommendation_thresholds.low_probability_threshold' => ['sometimes', 'numeric', 'between:0,100'],
            'recommendation_thresholds.closing_soon_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'recommendation_thresholds.inactive_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('name') && $this->has('forecast_name')) {
            $this->merge(['name' => $this->input('forecast_name')]);
        }

        if (! $this->has('forecast_name') && $this->has('name')) {
            $this->merge(['forecast_name' => $this->input('name')]);
        }

        if (! $this->has('forecast_start_date')) {
            $start = $this->input('period_start') ?? $this->input('start_period');
            if ($start) {
                $this->merge(['forecast_start_date' => $start]);
            }
        }

        if (! $this->has('forecast_end_date')) {
            $end = $this->input('period_end') ?? $this->input('end_period');
            if ($end) {
                $this->merge(['forecast_end_date' => $end]);
            }
        }

        if (! $this->has('period_start') && $this->has('start_period')) {
            $this->merge(['period_start' => $this->input('start_period')]);
        }

        if (! $this->has('start_period') && $this->has('period_start')) {
            $this->merge(['start_period' => $this->input('period_start')]);
        }

        if (! $this->has('period_end') && $this->has('end_period')) {
            $this->merge(['period_end' => $this->input('end_period')]);
        }

        if (! $this->has('end_period') && $this->has('period_end')) {
            $this->merge(['end_period' => $this->input('period_end')]);
        }

        if (! $this->has('forecast_type') || ! $this->input('forecast_type')) {
            $this->merge(['forecast_type' => ForecastType::OPPORTUNITY->value]);
        }

        if (! $this->has('revenue_category') || ! $this->input('revenue_category')) {
            $this->merge(['revenue_category' => 'ALL']);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $forecastStart = $this->dateValue($this->input('forecast_start_date'));
            $forecastEnd = $this->dateValue($this->input('forecast_end_date'));
            $historicalStart = $this->dateValue($this->input('historical_start_date'));
            $historicalEnd = $this->dateValue($this->input('historical_end_date'));

            if ($forecastStart && $forecastEnd && $forecastEnd->lt($forecastStart)) {
                $validator->errors()->add('forecast_end_date', 'Periode forecast harus berakhir setelah tanggal mulai.');
            }

            if ($historicalStart && $historicalEnd && $historicalEnd->lt($historicalStart)) {
                $validator->errors()->add('historical_end_date', 'Periode historis harus berakhir setelah tanggal mulai.');
            }

            if ($forecastStart && $historicalEnd && $historicalEnd->gte($forecastStart)) {
                $validator->errors()->add(
                    'historical_end_date',
                    'Periode historis tidak boleh mencakup periode forecast untuk menghindari data leakage.'
                );
            }
        });
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if (! $value || ! is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
