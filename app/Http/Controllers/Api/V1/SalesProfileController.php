<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\UpdateSalesProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Sales\SalesPerformanceService;
use App\Support\ApiResponse;
use App\Support\RoleChecks;
use Illuminate\Http\Request;

class SalesProfileController extends Controller
{
    use RoleChecks;

    public function __construct(private readonly SalesPerformanceService $performanceService) {}

    public function index()
    {
        $rows = $this->performanceService->forUsers($this->salesUsers());

        return ApiResponse::success(
            $rows->map(fn (array $row) => [
                'id' => $row['user']->id,
                'name' => $row['user']->name,
                'email' => $row['user']->email,
                'status' => $row['user']->status,
                'performance' => $row['performance'],
            ])->values(),
            'Sales profiles retrieved successfully'
        );
    }

    public function show(Request $request, int $id)
    {
        $sales = $this->findSales($id);

        if (! $sales) {
            return ApiResponse::notFound('Sales profile not found');
        }

        if ($request->user()->id !== $sales->id && ! $this->isViewer($request->user())) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success($this->profilePayload($sales), 'Sales profile retrieved successfully');
    }

    public function update(UpdateSalesProfileRequest $request, int $id)
    {
        $sales = $this->findSales($id);

        if (! $sales) {
            return ApiResponse::notFound('Sales profile not found');
        }

        $isViewer = $this->isViewer($request->user());

        if ($request->user()->id !== $sales->id && ! $isViewer) {
            return ApiResponse::forbidden();
        }

        $data = $request->validated();

        if (! $isViewer) {
            unset($data['status']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $sales->update($data);

        return ApiResponse::success($this->profilePayload($sales->fresh()), 'Sales profile updated successfully');
    }

    private function findSales(int $id): ?User
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::SALES->value))
            ->whereKey($id)
            ->first();
    }

    private function profilePayload(User $sales): array
    {
        $rows = $this->performanceService->forUsers(collect([$sales]));
        $performance = $rows->first()['performance'] ?? null;

        return [
            'user' => (new UserResource($sales->load('role')))->toArray(request()),
            'performance' => $performance,
        ];
    }

    private function salesUsers()
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('name', Role::SALES->value))
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'status']);
    }
}
