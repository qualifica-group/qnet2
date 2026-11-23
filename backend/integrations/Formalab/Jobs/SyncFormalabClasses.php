<?php

declare(strict_types=1);

namespace Integrations\Formalab\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Support\Trigger;

/**
 * Queued catalogue sync. Unique: a second dispatch while one is pending or
 * running is dropped instead of piling up.
 */
class SyncFormalabClasses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Seconds the uniqueness lock outlives a crashed worker. */
    public int $uniqueFor = 900;

    public function __construct(public readonly Trigger $trigger = Trigger::Schedule) {}

    public function handle(ClassCatalogSync $sync): void
    {
        $sync->handle($this->trigger);
    }
}
