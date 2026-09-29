<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email of the first-access flow (spec 0177), queued and localized like
 * ResetPasswordNotification (the locale comes from User::preferredLocale()).
 *
 * Two variants, selected by the token: a non-null `$token` is the INVITE (the
 * link opens the set-password page); null is the TEMPORARY-PASSWORD variant (the
 * link opens the login; the password itself is never put in the email).
 */
class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $token = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function isInvite(): bool
    {
        return $this->token !== null;
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => config('app.name')]))
            ->view(
                ['emails.welcome-user', 'emails.welcome-user-plain'],
                [
                    'appName' => config('app.name'),
                    'name' => $notifiable->getAttribute('name'),
                    'isInvite' => $this->isInvite(),
                    'url' => $this->actionUrl($notifiable),
                    'expireHours' => (int) round((int) config('auth.passwords.users_setup.expire') / 60),
                ],
            );
    }

    /** The link opens the SPA frontend, not the backend. */
    private function actionUrl(CanResetPassword $notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        if (! $this->isInvite()) {
            return "{$base}/login";
        }

        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return "{$base}/set-password?{$query}";
    }
}
