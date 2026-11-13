<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

/**
 * Puts the `mail` channel of a queued notification on its own queue
 * (config `mail.notifications_queue`). A mail provider outage then backs up
 * only that queue; every other channel (the bell's `database`) stays on the
 * notification's default queue and keeps being delivered.
 */
trait QueuesMailSeparately
{
    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => config('mail.notifications_queue')];
    }
}
