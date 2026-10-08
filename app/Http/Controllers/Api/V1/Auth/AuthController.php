<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Handle user registration.
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Registration successful.', 201);
    }

    /**
     * Handle user authentication.
     */
    public function login(LoginRequest $request, AuthenticateUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 'Login successful.');
    }

    /**
     * Handle user logout and revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Return the current authenticated user with associated companies.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()?->load('companies');

        return ApiResponse::success(
            new UserResource($user),
            'Current user retrieved successfully.',
        );
    }
}
