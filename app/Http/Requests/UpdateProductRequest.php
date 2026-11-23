<?php

namespace App\Http\Requests;

use App\Enums\EntityStatus;
use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal_id' => ['sometimes', 'integer', 'exists:principals,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'product_type' => ['sometimes', Rule::in(ProductType::values())],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'type' => ['sometimes', Rule::in(ProductType::values())],
            'description' => ['sometimes', 'nullable', 'string'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(EntityStatus::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('product_type') && $this->has('type')) {
            $this->merge(['product_type' => $this->input('type')]);
        }

        if (! $this->has('type') && $this->has('product_type')) {
            $this->merge(['type' => $this->input('product_type')]);
        }
    }
}
