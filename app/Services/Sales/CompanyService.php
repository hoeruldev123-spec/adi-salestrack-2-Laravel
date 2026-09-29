<?php

namespace App\Services\Sales;

use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;

class CompanyService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Company::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Company
    {
        return Company::create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company->fresh();
    }

    public function delete(Company $company): void
    {
        $company->delete();
    }
}
