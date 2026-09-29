<?php

namespace App\Policies;

use App\Models\ForecastRun;
use App\Models\User;
use App\Support\RoleChecks;

class ForecastRunPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return $this->isViewer($user);
    }

    public function view(User $user, ForecastRun $run): bool
    {
        return $this->isViewer($user) || $run->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }

    public function update(User $user, ForecastRun $run): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ForecastRun $run): bool
    {
        return $this->isAdmin($user);
    }
}
