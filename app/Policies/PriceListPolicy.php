<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;
use App\Support\RoleChecks;

class PriceListPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $this->isAdmin($user);
    }
}
