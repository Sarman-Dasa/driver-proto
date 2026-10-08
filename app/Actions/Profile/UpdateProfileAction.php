<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\User;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Support\Facades\Notification;

class UpdateProfileAction
{
    /**
     * Update user profile with secure email change flow.
     *
     * @param  array{name: string, email: string}  $data
     * @return array{user: User, email_change_pending: bool}
     */
    public function execute(User $user, array $data): array
    {
        $user->name = $data['name'];
        $emailChanged = strtolower(trim($data['email'])) !== strtolower(trim($user->email));

        if ($emailChanged) {
            $user->pending_email = strtolower(trim($data['email']));
            $user->save();

            // Send verification email to the new address
            Notification::route('mail', $user->pending_email)
                ->notify(new VerifyPendingEmailNotification($user->pending_email));

            return [
                'user' => $user->load('companies'),
                'email_change_pending' => true,
            ];
        }

        // If the user set email back to their current email, cancel any pending change
        if ($user->pending_email !== null) {
            $user->pending_email = null;
        }

        $user->save();

        return [
            'user' => $user->load('companies'),
            'email_change_pending' => false,
        ];
    }
}
