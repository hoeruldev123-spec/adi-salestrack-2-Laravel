<?php

namespace App\Policies;

use App\Models\ProductSpecification;
use App\Models\User;
use App\Support\RoleChecks;

class ProductSpecificationPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductSpecification $specification): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ProductSpecification $specification): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ProductSpecification $specification): bool
    {
        return $this->isAdmin($user);
    }
}
