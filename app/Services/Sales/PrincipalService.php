<?php

namespace App\Services\Sales;

use App\Models\MasterPrincipal;
use Illuminate\Pagination\LengthAwarePaginator;

class PrincipalService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return MasterPrincipal::query()
            ->withCount('products')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): MasterPrincipal
    {
        return MasterPrincipal::create($data);
    }

    public function update(MasterPrincipal $principal, array $data): MasterPrincipal
    {
        $principal->update($data);

        return $principal->fresh();
    }

    public function delete(MasterPrincipal $principal): void
    {
        $principal->delete();
    }
}
