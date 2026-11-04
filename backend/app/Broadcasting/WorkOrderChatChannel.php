<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\Chat\WorkOrderChatAccess;

/**
 * The private work order chat channel (spec 0352, D-9.c): the ONLY place that
 * generates its name. The frontend receives it from the API and never builds it.
 */
final class WorkOrderChatChannel
{
    /** Registration pattern; `{workOrder}` is route-model bound, a missing record denies. */
    public const string PATTERN = 'work-orders.{workOrder}.chat';

    public function __construct(private readonly WorkOrderChatAccess $access) {}

    public static function name(WorkOrder|int $workOrder): string
    {
        $id = $workOrder instanceof WorkOrder ? $workOrder->id : $workOrder;

        return str_replace('{workOrder}', (string) $id, self::PATTERN);
    }

    public function join(User $user, WorkOrder $workOrder): bool
    {
        return $this->access->canRead($user, $workOrder);
    }
}
