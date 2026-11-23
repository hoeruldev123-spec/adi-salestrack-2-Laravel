<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'pricing_type' => ['sometimes', Rule::in(PricingType::values())],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_period' => ['sometimes', Rule::in(['MONTHLY', 'YEARLY', 'ONE_TIME'])],
            'effective_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
