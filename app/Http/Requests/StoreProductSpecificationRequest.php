<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specification_name' => ['required', 'string', 'max:100'],
            'specification_value' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
        ];
    }
}
