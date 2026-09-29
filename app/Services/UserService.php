<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($filters['role_id'] ?? null, fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->fresh('role');
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function roles(): Collection
    {
        return Role::orderBy('id')->get();
    }
}
