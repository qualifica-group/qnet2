<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;

/**
 * A WorkOrder's computed status and completion percentage (spec 0149), read
 * together because both derive from the same root-task aggregates.
 */
final readonly class WorkOrderProgress
{
    public function __construct(
        public WorkOrderStatus $status,
        public int $completionPercentage,
    ) {}
}
