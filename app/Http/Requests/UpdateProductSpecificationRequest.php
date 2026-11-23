<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specification_name' => ['sometimes', 'string', 'max:100'],
            'specification_value' => ['sometimes', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
