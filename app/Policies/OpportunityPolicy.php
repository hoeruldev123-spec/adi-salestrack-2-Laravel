<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use App\Support\RoleChecks;

class OpportunityPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->isViewer($user) || $opportunity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user) || $this->isSales($user);
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $this->isAdmin($user) || $this->isManager($user) || $opportunity->user_id === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->isAdmin($user) || $opportunity->user_id === $user->id;
    }
}
