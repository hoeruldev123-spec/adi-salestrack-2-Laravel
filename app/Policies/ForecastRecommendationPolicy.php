<?php

namespace App\Policies;

use App\Models\ForecastRecommendation;
use App\Models\User;
use App\Support\RoleChecks;

class ForecastRecommendationPolicy
{
    use RoleChecks;

    public function viewAny(User $user): bool
    {
        return $this->isViewer($user);
    }

    public function view(User $user, ForecastRecommendation $recommendation): bool
    {
        return $this->isViewer($user) || $recommendation->forecastRun?->created_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }

    public function update(User $user, ForecastRecommendation $recommendation): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }

    public function delete(User $user, ForecastRecommendation $recommendation): bool
    {
        return $this->isAdmin($user) || $this->isManager($user);
    }
}
