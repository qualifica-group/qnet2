<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued like the other mail-bearing notifications (NoteMentionNotification,
 * RequestTransferredNotification): a slow or unreachable SMTP host must not
 * hold the forgot-password response open, since that endpoint answers with a
 * generic message regardless of delivery outcome.
 *
 * The locale survives the queue hop because User implements
 * HasLocalePreference: NotificationSender resolves it per-notifiable in the
 * worker, not from the request-time App::setLocale().
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(CanResetPassword $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->view(
                ['emails.reset-password', 'emails.reset-password-plain'],
                [
                    'appName' => config('app.name'),
                    'name' => $notifiable->getAttribute('name'),
                    'url' => $this->resetUrl($notifiable),
                    'expireMinutes' => (int) config('auth.passwords.users.expire', 60),
                ],
            );
    }

    /** Builds the reset link that opens the SPA frontend, not the backend. */
    private function resetUrl(CanResetPassword $notifiable): string
    {
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return rtrim((string) config('app.frontend_url'), '/')."/reset-password?{$query}";
    }
}
