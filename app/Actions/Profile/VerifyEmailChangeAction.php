<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class VerifyEmailChangeAction
{
    /**
     * Verify pending email change and activate new email.
     *
     * @throws ValidationException
     */
    public function execute(User $user, string $hash, ?int $expires = null): User
    {
        if (empty($user->pending_email)) {
            throw ValidationException::withMessages([
                'email' => ['No pending email change request found.'],
            ]);
        }

        if ($expires !== null && $expires < now()->timestamp) {
            throw ValidationException::withMessages([
                'email' => ['The email verification link has expired.'],
            ]);
        }

        if (! hash_equals(sha1($user->pending_email), $hash)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email verification link.'],
            ]);
        }

        // Activate new email address
        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->email_verified_at = now();
        $user->save();

        return $user->load('companies');
    }
}
