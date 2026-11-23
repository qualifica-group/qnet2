<?php

declare(strict_types=1);

namespace Integrations\Formalab\Console;

use Illuminate\Console\Command;
use Integrations\Formalab\Models\FormalabSyncRun;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;

class SyncClassesCommand extends Command
{
    protected $signature = 'formalab:sync-classes';

    protected $description = 'Sync the Formalab class catalogue and update the linked work orders';

    public function handle(ClassCatalogSync $sync): int
    {
        $run = $sync->handle(Trigger::Manual);

        if ($run->outcome === FormalabSyncRun::OUTCOME_FAILURE) {
            $this->error((string) $run->error_message);

            return self::FAILURE;
        }

        $this->info("Classes synced: {$run->classes_synced}; work orders updated: {$run->work_orders_updated}");

        return self::SUCCESS;
    }
}
