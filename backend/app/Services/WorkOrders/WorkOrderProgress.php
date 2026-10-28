<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;

/**
 * A WorkOrder's computed status, completion percentage and the date it was
 * reached (spec 0321), read together because all derive from the same manual
 * progress entries.
 */
final readonly class WorkOrderProgress
{
    public function __construct(
        public WorkOrderStatus $status,
        public int $completionPercentage,
        public ?string $recordedOn,
    ) {}
}
