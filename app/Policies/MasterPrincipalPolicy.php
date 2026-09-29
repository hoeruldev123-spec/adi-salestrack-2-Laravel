<?php

namespace App\Policies;

use App\Models\MasterPrincipal;
use App\Models\User;
use App\Support\RoleChecks;

class MasterPrincipalPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MasterPrincipal $principal): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, MasterPrincipal $principal): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, MasterPrincipal $principal): bool
    {
        return $this->isAdmin($user);
    }
}
