<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\WorkOrderWorkflowStatus;

/**
 * Resolves the `work_order_workflow_status_old_id` a legacy task template
 * action or task carries (spec 0323, D-9): the legacy `progress.id`, already
 * cleaned by the sender, looked up on `work_order_workflow_statuses.old_id`.
 * A status not migrated yet is left empty with a warning, never invented.
 */
final class LegacyWorkOrderStatusLink
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function resolve(mixed $legacyStatusId, array &$warnings): ?int
    {
        if ($legacyStatusId === null || $legacyStatusId === '') {
            return null;
        }

        $statusId = WorkOrderWorkflowStatus::query()->where('old_id', (int) $legacyStatusId)->value('id');

        if ($statusId === null) {
            $warnings[] = "Work order workflow status (old_id {$legacyStatusId}) not found; the working status link was left empty.";

            return null;
        }

        return (int) $statusId;
    }
}
