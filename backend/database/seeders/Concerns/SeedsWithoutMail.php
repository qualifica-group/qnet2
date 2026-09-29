<?php

namespace Database\Seeders\Concerns;

use Database\Seeders\Support\MailSuppressingChannelManager;
use Illuminate\Support\Facades\Notification;

/**
 * Runs a seeding callback with the mail channel silenced: the write paths the
 * seeders go through (TaskService, OpportunityService's AssignmentNotifier,
 * ...) still leave their in-app notifications, but no email leaves the seed.
 * The previous dispatcher is restored afterwards, so a caller that faked it —
 * or a later seeder in the same process — keeps its own.
 */
trait SeedsWithoutMail
{
    protected function withoutMail(callable $seed): void
    {
        $notifications = Notification::getFacadeRoot();
        Notification::swap(new MailSuppressingChannelManager(app()));

        try {
            $seed();
        } finally {
            Notification::swap($notifications);
        }
    }
}
