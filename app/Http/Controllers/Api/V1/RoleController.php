<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Master\RoleResource;
use App\Services\UserService;
use App\Support\ApiResponse;

class RoleController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index()
    {
        return ApiResponse::success(
            RoleResource::collection($this->userService->roles()),
            'Roles retrieved successfully'
        );
    }
}
