<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'principal_id' => ['sometimes', 'integer', 'exists:master_principals,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'stage' => ['sometimes', Rule::in(OpportunityStage::values())],
            'status' => ['sometimes', Rule::in(OpportunityStatus::values())],
            'probability' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'source' => ['sometimes', 'nullable', 'string', 'max:100'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
