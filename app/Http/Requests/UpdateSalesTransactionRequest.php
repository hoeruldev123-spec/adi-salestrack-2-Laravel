<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opportunity_id' => ['sometimes', 'nullable', 'integer', 'exists:opportunities,id'],
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'transaction_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(TransactionStatus::values())],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
