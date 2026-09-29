<?php

namespace App\Services\Opportunity;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityService
{
    public function paginate(Opportunity $opportunity, array $filters): LengthAwarePaginator
    {
        return $opportunity->activities()
            ->with('user')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('activity_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(Opportunity $opportunity, array $data, User $user): Activity
    {
        $data['opportunity_id'] = $opportunity->id;
        $data['user_id'] = $user->id;

        return Activity::create($data);
    }

    public function update(Activity $activity, array $data): Activity
    {
        $activity->update($data);

        return $activity->fresh('user');
    }

    public function destroy(Activity $activity): void
    {
        $activity->delete();
    }
}
