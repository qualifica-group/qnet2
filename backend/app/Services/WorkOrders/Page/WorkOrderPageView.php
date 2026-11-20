<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Page;

use App\Models\Task;
use App\Models\WorkOrderPage;
use App\Models\WorkOrderPagePoll;
use Illuminate\Support\Collection;

/**
 * Everything the WorkOrderPagePayload (spec 0420 data_contract) is made of:
 * the page (null while it does not exist yet), the tasks cited by its content
 * (id => visible Task, or null when not available to the reader) and the page polls.
 */
final readonly class WorkOrderPageView
{
    /**
     * @param  array<int, Task|null>  $tasks
     * @param  Collection<int, WorkOrderPagePoll>  $polls
     */
    public function __construct(
        public ?WorkOrderPage $page,
        public array $tasks,
        public Collection $polls,
        public bool $canEdit,
    ) {}
}
