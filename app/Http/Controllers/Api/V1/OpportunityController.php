<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpportunityRequest;
use App\Http\Requests\UpdateOpportunityRequest;
use App\Http\Resources\Opportunity\OpportunityItemResource;
use App\Http\Resources\Opportunity\OpportunityLogResource;
use App\Http\Resources\Opportunity\OpportunityResource;
use App\Models\Opportunity;
use App\Services\Opportunity\OpportunityService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function __construct(private readonly OpportunityService $opportunityService)
    {
        $this->authorizeResource(Opportunity::class, 'opportunity');
    }

    public function index(Request $request)
    {
        $opportunities = $this->opportunityService->paginate(
            $request->only(['status', 'stage', 'company_id', 'principal_id', 'user_id', 'search', 'per_page']),
            $request->user()
        );

        return ApiResponse::paginated(
            OpportunityResource::collection($opportunities),
            'Opportunities retrieved successfully'
        );
    }

    public function show(Opportunity $opportunity)
    {
        return ApiResponse::success(
            new OpportunityResource(
                $opportunity->load(['company', 'user', 'principal', 'items.product'])
            ),
            'Opportunity retrieved successfully'
        );
    }

    public function store(StoreOpportunityRequest $request)
    {
        $opportunity = $this->opportunityService->create($request->validated(), $request->user());

        return ApiResponse::created(
            new OpportunityResource(
                $opportunity->load(['company', 'user', 'principal', 'items.product'])
            ),
            'Opportunity created successfully'
        );
    }

    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity)
    {
        $opportunity = $this->opportunityService->update($opportunity, $request->validated(), $request->user());

        return ApiResponse::success(
            new OpportunityResource(
                $opportunity->load(['company', 'user', 'principal', 'items.product'])
            ),
            'Opportunity updated successfully'
        );
    }

    public function destroy(Opportunity $opportunity)
    {
        $this->opportunityService->destroy($opportunity);

        return ApiResponse::success(null, 'Opportunity deleted successfully');
    }

    public function items(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        return ApiResponse::success(
            OpportunityItemResource::collection($opportunity->items()->with('product')->get()),
            'Opportunity items retrieved successfully'
        );
    }

    public function logs(Opportunity $opportunity)
    {
        $this->authorize('view', $opportunity);

        $logs = $this->opportunityService->logs($opportunity);

        return ApiResponse::paginated(
            OpportunityLogResource::collection($logs),
            'Opportunity logs retrieved successfully'
        );
    }
}
