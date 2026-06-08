<?php

declare(strict_types=1);

namespace Domain\User\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite email for a new company seat. Links to the standard end-user password
 * reset route (the `users` broker) so the invitee sets their own password and
 * can sign in. Used only for the initial invite; ordinary forgot-password keeps
 * the default Laravel notification.
 */
class UserInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
        public string $companyName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('You have been invited to CellarOS')
            ->greeting('Welcome to CellarOS')
            ->line("You've been invited to join {$this->companyName} on CellarOS. Set your password to get started.")
            ->action('Set your password', $url)
            ->line('If you did not expect this invitation, no action is required.');
    }
}
