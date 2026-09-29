<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Sales\SalesPerformanceService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly SalesPerformanceService $performanceService) {}

    public function show(Request $request)
    {
        $user = $request->user()->load('role');

        $data = (new UserResource($user))->toArray($request);

        if ($user->role?->name === Role::SALES->value) {
            $data['performance'] = $this->performanceService->forUser($user);
        }

        return ApiResponse::success($data, 'Profile retrieved successfully');
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return ApiResponse::success(
            new UserResource($user->fresh('role')),
            'Profile updated successfully'
        );
    }
}
