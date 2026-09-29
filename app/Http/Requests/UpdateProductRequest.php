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
            'principal_id' => ['sometimes', 'integer', 'exists:master_principals,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'type' => ['sometimes', Rule::in(ProductType::values())],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(EntityStatus::values())],
        ];
    }
}
