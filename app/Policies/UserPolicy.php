<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleChecks;

class UserPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }

    public function view(User $user, ?User $model = null): bool
    {
        return $this->isViewer($user) || ($model && $user->id === $model->id);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $user->id !== $model->id;
    }
}
