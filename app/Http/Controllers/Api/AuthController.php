<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return ApiResponse::success([
            'user' => UserResource::make($user),
            'access_token' => auth('api')->login($user),
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 'User registered successfully.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! $token = auth('api')->attempt($request->validated())) {
            return ApiResponse::error(
                'The provided credentials are incorrect.',
                'INVALID_CREDENTIALS',
                401,
            );
        }

        return $this->tokenResponse($token);
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(UserResource::make(auth('api')->user()), 'Authenticated user retrieved successfully.');
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return ApiResponse::success(null, 'Successfully logged out.');
    }

    public function refresh(): JsonResponse
    {
        return $this->tokenResponse(auth('api')->refresh());
    }

    private function tokenResponse(string $token): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make(auth('api')->user()),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ], 'Authentication token issued successfully.');
    }
}
