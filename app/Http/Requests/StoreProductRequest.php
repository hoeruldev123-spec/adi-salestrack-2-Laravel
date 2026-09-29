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
            'principal_id' => ['required', 'integer', 'exists:master_principals,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'type' => ['sometimes', Rule::in(ProductType::values())],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(EntityStatus::values())],
        ];
    }
}
