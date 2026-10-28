<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\WorkOrderWorkflowStatus;

/**
 * The `select` editor catalogue of the work-orders grid's
 * `work_order_workflow_status` column (spec 0322): every set's statuses (GET
 * /columns is domain-wide, not per-row), each carrying `color` for the option
 * dot. The per-row narrowing rides on the row's own
 * `work_order_workflow_status_options`; the authoritative membership check
 * stays in WorkOrderWorkflowStatusAssigner. Twin of QuoteWorkflowStatusOptions.
 */
final class WorkOrderWorkflowStatusOptions
{
    /**
     * @return array<int, array{value: int, label: string, color: string|null}>
     */
    public static function catalog(): array
    {
        return WorkOrderWorkflowStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color'])
            ->map(static fn (WorkOrderWorkflowStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
            ])
            ->all();
    }
}
