<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordAction
{
    /**
     * Change user password after verifying current password and revoke other tokens.
     *
     * @param  array{current_password: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function execute(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        // Invalidate other active tokens while keeping the current session active
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId !== null) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }
    }
}
