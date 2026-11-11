<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * The one place that decides whether an in-app notification also goes out
 * by email: the bell entry is always written, the email copy only when the
 * recipient keeps their "email notifications" switch on (settings page) and
 * has an address. Credential emails (welcome, password reset) do not use this.
 */
trait RespectsEmailPreference
{
    /**
     * @return array<int, string>
     */
    protected function databaseAndPreferredMail(object $notifiable): array
    {
        return $notifiable instanceof User && $notifiable->wantsEmailNotifications()
            ? ['database', 'mail']
            : ['database'];
    }
}
