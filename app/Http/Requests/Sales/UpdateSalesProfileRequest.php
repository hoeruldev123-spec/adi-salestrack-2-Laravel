<?php

namespace App\Http\Requests\Sales;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('id'))],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'status' => ['sometimes', Rule::in(UserStatus::values())],
        ];
    }
}
