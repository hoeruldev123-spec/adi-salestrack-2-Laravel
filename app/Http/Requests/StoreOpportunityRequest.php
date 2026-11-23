<?php

namespace App\Http\Requests;

use App\Enums\OpportunityStage;
use App\Enums\OpportunityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required_without:client_name', 'nullable', 'integer', 'exists:companies,id'],
            'principal_id' => ['nullable', 'integer', 'exists:principals,id'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'title' => ['required_without:project_name', 'nullable', 'string', 'max:255'],
            'project_name' => ['required_without:title', 'nullable', 'string', 'max:200'],
            'client_name' => ['required_without:company_id', 'nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'stage' => ['sometimes', Rule::in(OpportunityStage::values())],
            'progress' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(OpportunityStatus::values())],
            'probability' => ['nullable', 'numeric', 'between:0,100'],
            'sa_pic' => ['nullable', 'string', 'max:150'],
            'license_type' => ['nullable', 'string', 'max:50'],
            'deal_registration' => ['nullable', 'string', 'max:100'],
            'closing_date' => ['nullable', 'date'],
            'expected_close_date' => ['nullable', 'date'],
            'actual_closing_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.price_list_id' => ['nullable', 'integer', 'exists:price_lists,id'],
            'items.*.item_description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('company_id') && $this->filled('client_name')) {
            $company = \App\Models\Company::firstOrCreate(
                ['name' => $this->input('client_name')],
                ['industry' => 'Technology']
            );
            $this->merge(['company_id' => $company->id]);
        }

        if (! $this->filled('client_name') && $this->filled('company_id')) {
            $company = \App\Models\Company::find($this->input('company_id'));
            if ($company) {
                $this->merge(['client_name' => $company->name]);
            }
        }

        if (! $this->has('title') && $this->has('project_name')) {
            $this->merge(['title' => $this->input('project_name')]);
        }

        if (! $this->has('project_name') && $this->has('title')) {
            $this->merge(['project_name' => $this->input('title')]);
        }

        if (! $this->has('value') && $this->has('estimated_value')) {
            $this->merge(['value' => $this->input('estimated_value')]);
        }

        if (! $this->has('estimated_value') && $this->has('value')) {
            $this->merge(['estimated_value' => $this->input('value')]);
        }

        if (! $this->has('progress') && $this->has('stage')) {
            $this->merge(['progress' => $this->input('stage')]);
        }

        if (! $this->has('expected_close_date') && $this->has('closing_date')) {
            $this->merge(['expected_close_date' => $this->input('closing_date')]);
        }

        if (! $this->has('closing_date') && $this->has('expected_close_date')) {
            $this->merge(['closing_date' => $this->input('expected_close_date')]);
        }
    }
}
