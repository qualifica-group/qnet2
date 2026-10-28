<?php

namespace App\Enums;

/**
 * The system rows a WorkOrderWorkflowStatus set (a workflow's own, or the
 * global default set) carries (spec 0322, D-2): an initial 'open' row and the
 * two pinned tail rows 'completed' and 'closed'. All three are MANDATORY,
 * non-deletable, and every set is created with them. Each one's `group` is
 * the `WorkOrderStatus` value equal to its key (D-7) and cannot change.
 *
 * Persisted as `work_order_workflow_statuses.system_key` (nullable — custom
 * rows have none). Never mass-assignable.
 */
enum WorkOrderWorkflowStatusSystemKey: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The pinned rows placed after every custom row, in fixed order.
     * 'open' is pinned FIRST and is not part of this tail.
     *
     * @return array<int, self>
     */
    public static function tailKeys(): array
    {
        return [self::Completed, self::Closed];
    }

    /**
     * The `WorkOrderStatus` group a system row of this key always carries.
     */
    public function group(): WorkOrderStatus
    {
        return WorkOrderStatus::from($this->value);
    }
}
