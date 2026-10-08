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
        /** @var User|null $user */
        $user = User::query()->find($request->input('id'));

        if (! $user) {
            return ApiResponse::error('User not found.', [], 404);
        }

        // 1. Check expiration if timestamp is passed
        $expires = $request->input('expires');
        if ($expires !== null && (int) $expires < now()->timestamp) {
            return ApiResponse::error('The verification link has expired.', [], 400);
        }

        // 2. Validate hash against user's email
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->input('hash'))) {
            return ApiResponse::error('Invalid email verification link.', [], 400);
        }

        // 3. Authorization check: authenticated user cannot verify another user's email
        if ($request->user() && $request->user()->id !== $user->id) {
            return ApiResponse::error('You are not authorized to verify this account.', [], 403);
        }

        // 4. Verify signature if provided
        $signature = $request->input('signature');
        if ($signature !== null && $expires !== null) {
            $expectedUrl = route('verification.verify', [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]);
            $expectedSignature = hash_hmac('sha256', $expectedUrl.'?expires='.$expires, config('app.key'));
            if (! hash_equals($expectedSignature, (string) $signature)) {
                return ApiResponse::error('Invalid verification signature.', [], 400);
            }
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
