<?php

namespace App\Http\Requests\Report;

use App\Enums\OpportunityStatus;
use Illuminate\Foundation\Http\FormRequest;

class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $endDate = ['sometimes', 'nullable', 'date_format:Y-m-d'];

        if ($this->filled('start_date')) {
            $endDate[] = 'after_or_equal:start_date';
        }

        return [
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => $endDate,
            'sales_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', 'in:'.implode(',', OpportunityStatus::values())],
        ];
    }

    public function filters(): array
    {
        return array_filter(
            $this->validated(),
            fn ($value) => $value !== null
        );
    }
}
