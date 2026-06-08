<?php

declare(strict_types=1);

namespace Domain\Supplier\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Invite / password-reset email for supplier portal users. Links to the
 * supplier portal's own reset route rather than the end-user one. Used both for
 * the initial admin invite and for self-service password resets.
 */
class SupplierPasswordSetupNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('supplier.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expireMinutes = (int) config('auth.passwords.supplier_users.expire');
        $expire = $expireMinutes % 60 === 0
            ? Lang::get(':count hours', ['count' => intdiv($expireMinutes, 60)])
            : Lang::get(':count minutes', ['count' => $expireMinutes]);

        return (new MailMessage)
            ->subject('Set up your CellarOS supplier account')
            ->greeting('Welcome to CellarOS')
            ->line('You have been invited to the CellarOS supplier portal. Use the button below to set your password and sign in.')
            ->action('Set your password', $url)
            ->line("This link will expire in {$expire}.")
            ->line('If you did not expect this invitation, no action is required.');
    }
}
