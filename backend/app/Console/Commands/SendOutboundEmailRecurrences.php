<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OutboundEmails\RecurringEmailDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Hourly entry point of the recurring emails (spec 0362, D-9): delegates to
 * RecurringEmailDispatcher, which sends nothing before
 * `outbound_emails.recurrence.send_at`. `--dry-run` only counts.
 */
class SendOutboundEmailRecurrences extends Command
{
    protected $signature = 'outbound-emails:send-recurrences {--dry-run}';

    protected $description = 'Send the due occurrences of the recurring outbound emails';

    public function handle(RecurringEmailDispatcher $dispatcher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = $dispatcher->handle(CarbonImmutable::now(config('app.timezone')), $dryRun);

        $this->info($dryRun ? "{$count} email(s) would be sent." : "{$count} email(s) sent.");

        return self::SUCCESS;
    }
}
