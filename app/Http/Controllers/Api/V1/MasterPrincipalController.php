<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrincipalRequest;
use App\Http\Requests\UpdatePrincipalRequest;
use App\Http\Resources\Master\PrincipalResource;
use App\Models\MasterPrincipal;
use App\Services\Sales\PrincipalService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class MasterPrincipalController extends Controller
{
    public function __construct(private readonly PrincipalService $principalService)
    {
        $this->authorizeResource(MasterPrincipal::class, 'principal');
    }

    public function index(Request $request)
    {
        $principals = $this->principalService->paginate($request->only(['search', 'status', 'per_page']));

        return ApiResponse::paginated(PrincipalResource::collection($principals), 'Principals retrieved successfully');
    }

    public function show(MasterPrincipal $principal)
    {
        return ApiResponse::success(
            new PrincipalResource($principal->loadCount('products')),
            'Principal retrieved successfully'
        );
    }

    public function store(StorePrincipalRequest $request)
    {
        $principal = $this->principalService->create($request->validated());

        return ApiResponse::created(new PrincipalResource($principal), 'Principal created successfully');
    }

    public function update(UpdatePrincipalRequest $request, MasterPrincipal $principal)
    {
        $principal = $this->principalService->update($principal, $request->validated());

        return ApiResponse::success(new PrincipalResource($principal), 'Principal updated successfully');
    }

    public function destroy(MasterPrincipal $principal)
    {
        $this->principalService->delete($principal);

        return ApiResponse::success(null, 'Principal deleted successfully');
    }
}
