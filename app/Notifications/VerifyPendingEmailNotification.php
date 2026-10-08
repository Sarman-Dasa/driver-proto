<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyPendingEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $pendingEmail,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(User $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'profile.verify-email-change',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($this->pendingEmail),
            ],
        );

        return (new MailMessage)
            ->subject('Verify Your New Email Address - BillFlow')
            ->line('You recently requested to change your email address for your BillFlow account.')
            ->line("Please click the button below to verify {$this->pendingEmail} as your new email address.")
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 60 minutes.')
            ->line('If you did not request this change, please ignore this email; your existing email will remain active.');
    }
}
