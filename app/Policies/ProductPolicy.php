<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Support\RoleChecks;

class ProductPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->isAdmin($user);
    }
}
