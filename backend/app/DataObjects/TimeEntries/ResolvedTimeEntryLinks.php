<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * The D-5 verdict of `TimeEntryLinkResolver::resolve()`: the four columns a
 * TimeEntry actually gets written with (`title` plus the three optional
 * record links), whether they came verbatim from a standalone payload or
 * were imposed by a linked Task. TimeEntryService writes these onto the
 * model directly — never the submitted `title`/`registry_id`/
 * `opportunity_id`/`work_order_id`/`task_id` of TimeEntryData, which this
 * DTO supersedes by construction.
 */
final readonly class ResolvedTimeEntryLinks
{
    public function __construct(
        public string $title,
        public ?int $registryId,
        public ?int $opportunityId,
        public ?int $workOrderId,
        public ?int $taskId,
    ) {}
}
