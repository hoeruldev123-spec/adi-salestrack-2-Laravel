<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        $users = $this->userService->paginate($request->only(['search', 'role_id', 'status', 'per_page']));

        return ApiResponse::paginated(UserResource::collection($users), 'Users retrieved successfully');
    }

    public function show(User $user)
    {
        return ApiResponse::success(
            new UserResource($user->load('role')),
            'User retrieved successfully'
        );
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return ApiResponse::created(
            new UserResource($user->load('role')),
            'User created successfully'
        );
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated());

        return ApiResponse::success(
            new UserResource($user->load('role')),
            'User updated successfully'
        );
    }

    public function destroy(User $user)
    {
        $this->userService->delete($user);

        return ApiResponse::success(null, 'User deleted successfully');
    }

    public function sales()
    {
        $users = User::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'SALES'))
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return ApiResponse::success($users, 'Sales users retrieved successfully');
    }
}
