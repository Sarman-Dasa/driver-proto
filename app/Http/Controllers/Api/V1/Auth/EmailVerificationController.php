<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $user = User::query()->find($request->input('id'));

        if (! $user) {
            return ApiResponse::error('User not found.', [], 404);
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->input('hash'))) {
            return ApiResponse::error('Invalid email verification link.', [], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return ApiResponse::success(null, 'Email has been successfully verified.');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', [], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'Email already verified.');
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Verification link sent!');
    }
}
