<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

/**
 * A WorkOrder's completion percentage and the date it was reached (spec
 * 0321), read together because both derive from the same manual progress
 * entries. The status is the persisted working phase (spec 0395).
 */
final readonly class WorkOrderProgress
{
    public function __construct(
        public int $completionPercentage,
        public ?string $recordedOn,
    ) {}
}
