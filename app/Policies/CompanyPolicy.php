<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleChecks;

class CompanyPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->isAdmin($user);
    }
}
