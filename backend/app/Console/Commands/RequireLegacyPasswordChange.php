<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off (spec 0355, D-4): forces the first-access password change on every
 * user imported from the legacy (`old_id` set). No email, no token revocation:
 * the gate shows at their next request. Idempotent.
 */
class RequireLegacyPasswordChange extends Command
{
    protected $signature = 'users:require-legacy-password-change';

    protected $description = 'Require a password change at next login for every user imported from the legacy';

    public function handle(): int
    {
        $count = User::query()
            ->whereNotNull('old_id')
            ->where('is_service_account', false)
            ->where('must_set_password', false)
            ->update(['must_set_password' => true]);

        $this->info("{$count} utenti legacy dovranno cambiare password al prossimo accesso.");

        return self::SUCCESS;
    }
}
