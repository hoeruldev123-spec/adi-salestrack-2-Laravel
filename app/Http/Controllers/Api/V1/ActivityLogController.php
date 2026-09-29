<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Opportunity\OpportunityLogResource;
use App\Models\OpportunityLog;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = OpportunityLog::query()
            ->with(['user:id,name', 'opportunity:id,title'])
            ->when($request->query('user_id'), fn ($query, $id) => $query->where('user_id', $id))
            ->when($request->query('opportunity_id'), fn ($query, $id) => $query->where('opportunity_id', $id))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(
            OpportunityLogResource::collection($logs),
            'Activity logs retrieved successfully'
        );
    }
}
