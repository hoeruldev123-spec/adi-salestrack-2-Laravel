<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::with('role')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return ApiResponse::unauthorized('Invalid credentials');
        }

        if ($user->status !== 'ACTIVE') {
            return ApiResponse::forbidden('User account is inactive');
        }

        $token = $user->createToken('api-token-'.$user->id);

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout successful');
    }

    public function me(Request $request)
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('role')),
            'User retrieved successfully'
        );
    }
}
