<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Profile\VerifyEmailChangeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Profile\VerifyEmailChangeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            new UserResource($user->load('companies')),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request, UpdateProfileAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $action->execute($user, $request->validated());

        $message = $result['email_change_pending']
            ? 'Profile updated. A verification link has been sent to your new email address. Your current email remains active until verified.'
            : 'Profile updated successfully.';

        return ApiResponse::success(
            new UserResource($result['user']),
            $message,
        );
    }

    /**
     * Verify and activate pending email change.
     */
    public function verifyEmailChange(
        VerifyEmailChangeRequest $request,
        VerifyEmailChangeAction $action,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $updatedUser = $action->execute(
            $user,
            $request->input('hash'),
            $request->has('expires') ? (int) $request->input('expires') : null,
        );

        return ApiResponse::success(
            new UserResource($updatedUser),
            'Email address successfully verified and updated.',
        );
    }

    /**
     * Cancel any pending email change.
     */
    public function cancelPendingEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->pending_email = null;
        $user->save();

        return ApiResponse::success(
            new UserResource($user->load('companies')),
            'Pending email change cancelled.',
        );
    }
}
