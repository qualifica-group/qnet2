<?php

declare(strict_types=1);

namespace Integrations\Formalab\Console;

use Illuminate\Console\Command;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\CatalogSyncLauncher;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;

class SyncClassesCommand extends Command
{
    protected $signature = 'formalab:sync-classes';

    protected $description = 'Sync the Formalab class catalogue and update the linked work orders';

    public function handle(CatalogSyncLauncher $launcher, ClassCatalogSync $sync): int
    {
        // Step 1: same gate as the button and the schedule: never two syncs together
        $run = $launcher->open(Trigger::Manual);

        if ($run === null) {
            $this->error(__('formalab::messages.sync_already_running'));

            return self::FAILURE;
        }

        // Step 2: run it here, not on the queue
        $run = $sync->handle($run);

        if ($run->outcome === FormalabSyncRun::OUTCOME_FAILURE) {
            $this->error((string) $run->error_message);

            return self::FAILURE;
        }

        $this->info("Classes synced: {$run->classes_synced}; work orders updated: {$run->work_orders_updated}");

        return self::SUCCESS;
    }
}
