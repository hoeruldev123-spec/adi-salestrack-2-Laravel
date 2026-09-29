<?php

namespace App\Policies;

use App\Models\SalesTransaction;
use App\Models\User;
use App\Support\RoleChecks;

class SalesTransactionPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SalesTransaction $transaction): bool
    {
        return $this->isViewer($user) || $transaction->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isSales($user);
    }

    public function update(User $user, SalesTransaction $transaction): bool
    {
        return $this->isAdmin($user) || $transaction->user_id === $user->id;
    }

    public function delete(User $user, SalesTransaction $transaction): bool
    {
        return $this->isAdmin($user) || $transaction->user_id === $user->id;
    }
}
