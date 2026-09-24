<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\Notification;

/**
 * Runs a seeding callback with notifications faked, for the sample seeders
 * that write through services notifying by mail (TaskService, the Task
 * completion actions): seeding a batch must not queue a mail per row. The
 * previous channel is restored afterwards, so a caller that faked it — or a
 * later seeder in the same process — keeps its own.
 */
trait SeedsWithoutNotifications
{
    protected function withoutNotifications(callable $seed): void
    {
        $notifications = Notification::getFacadeRoot();
        Notification::fake();

        try {
            $seed();
        } finally {
            Notification::swap($notifications);
        }
    }
}
