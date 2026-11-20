<?php

declare(strict_types=1);

namespace Integrations\Formalab\Console;

use Illuminate\Console\Command;
use Integrations\Formalab\Services\LinkBackfill;

class LinkWorkOrdersCommand extends Command
{
    protected $signature = 'formalab:link-work-orders';

    protected $description = 'Link the work orders whose course name matches exactly one Formalab class';

    public function handle(LinkBackfill $backfill): int
    {
        $result = $backfill->handle();

        $this->info("Linked: {$result['linked']}; ambiguous: {$result['ambiguous']}; unmatched: {$result['unmatched']}");

        return self::SUCCESS;
    }
}
