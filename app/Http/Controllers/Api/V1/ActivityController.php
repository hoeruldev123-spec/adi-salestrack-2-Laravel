<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\Opportunity\ActivityResource;
use App\Models\Activity;
use App\Models\Opportunity;
use App\Services\Opportunity\ActivityService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private readonly ActivityService $activityService)
    {
        $this->authorizeResource(Activity::class, 'activity');
    }

    public function index(Request $request, Opportunity $opportunity)
    {
        $activities = $this->activityService->paginate(
            $opportunity,
            $request->only(['type', 'status', 'per_page'])
        );

        return ApiResponse::paginated(
            ActivityResource::collection($activities),
            'Activities retrieved successfully'
        );
    }

    public function store(StoreActivityRequest $request, Opportunity $opportunity)
    {
        $activity = $this->activityService->create($opportunity, $request->validated(), $request->user());

        return ApiResponse::created(
            new ActivityResource($activity->load('user')),
            'Activity created successfully'
        );
    }

    public function show(Activity $activity)
    {
        return ApiResponse::success(
            new ActivityResource($activity->load('user')),
            'Activity retrieved successfully'
        );
    }

    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $activity = $this->activityService->update($activity, $request->validated());

        return ApiResponse::success(
            new ActivityResource($activity->load('user')),
            'Activity updated successfully'
        );
    }

    public function destroy(Activity $activity)
    {
        $this->activityService->destroy($activity);

        return ApiResponse::success(null, 'Activity deleted successfully');
    }
}
