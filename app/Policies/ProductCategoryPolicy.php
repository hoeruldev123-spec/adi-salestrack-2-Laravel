<?php

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;
use App\Support\RoleChecks;

class ProductCategoryPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->isAdmin($user);
    }
}
