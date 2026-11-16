<?php

namespace App\Notifications;

use App\Notifications\Concerns\QueuesMailSeparately;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Welcome email (specs 0177, 0355): carries the generated password and a link to
 * the login; the user is asked to choose a new password on first access. Queued
 * and localized like ResetPasswordNotification (locale from
 * User::preferredLocale()). ShouldBeEncrypted keeps the password encrypted in
 * the queue payload (`jobs` / `failed_jobs`).
 */
class WelcomeUserNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, QueuesMailSeparately;

    public function __construct(private readonly string $password) {}

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
            ->subject(__('Welcome to :app', ['app' => config('app.name')]))
            ->view(
                ['emails.welcome-user', 'emails.welcome-user-plain'],
                [
                    'appName' => config('app.name'),
                    'name' => $notifiable->getAttribute('name'),
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'password' => $this->password,
                    'url' => $this->loginUrl(),
                ],
            );
    }

    /** The link opens the SPA frontend, not the backend. */
    private function loginUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/login';
    }
}
