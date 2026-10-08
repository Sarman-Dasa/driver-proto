<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUserAction
{
    /**
     * Execute authentication and return user with token.
     *
     * @param  array{email: string, password: string, device_name?: ?string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function execute(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $tokenName = ! empty($credentials['device_name'])
            ? $credentials['device_name']
            : 'auth_token';

        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => $user->load('companies'),
            'token' => $token,
        ];
    }
}
