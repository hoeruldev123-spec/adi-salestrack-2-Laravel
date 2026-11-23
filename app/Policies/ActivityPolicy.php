<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Support\RoleChecks;

class ActivityPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->isViewer($user) || $activity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user) || $this->isSales($user);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->isAdmin($user) || $this->isManager($user) || $activity->user_id === $user->id;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->isAdmin($user) || $activity->user_id === $user->id;
    }
}
