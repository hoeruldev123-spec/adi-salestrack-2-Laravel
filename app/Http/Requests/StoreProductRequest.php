<?php

namespace App\Http\Requests;

use App\Enums\EntityStatus;
use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'principal_id' => ['required', 'integer', 'exists:principals,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'product_type' => ['sometimes', Rule::in(ProductType::values())],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'type' => ['sometimes', Rule::in(ProductType::values())],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
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
