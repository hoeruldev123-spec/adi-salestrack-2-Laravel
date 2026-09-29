<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

trait RoleChecks
{
    public function isAdmin(User $user): bool
    {
        return $user->role?->name === Role::ADMIN->value;
    }

    public function isSales(User $user): bool
    {
        return $user->role?->name === Role::SALES->value;
    }

    public function isManager(User $user): bool
    {
        return $user->role?->name === Role::SALES_MANAGER->value;
    }

    public function isManagement(User $user): bool
    {
        return $user->role?->name === Role::MANAGEMENT->value;
    }

    public function isViewer(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user) || $this->isManagement($user);
    }
}
