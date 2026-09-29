<?php

namespace App\Http\Requests;

use App\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'transaction_date' => ['required', 'date'],
            'status' => ['sometimes', Rule::in(TransactionStatus::values())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
