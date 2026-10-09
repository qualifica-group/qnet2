<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
use App\Services\Quotes\RevenueProductTitleBuilder;

/**
 * Single owner of `work_orders.title` writes (spec 0215, D-2), the Commessa
 * twin of QuoteTitleWriter: the automatic title (`<code> - <products>`) follows
 * the commessa's own lines until the user types their own, flagged
 * `title_is_manual`.
 */
final class WorkOrderTitleWriter
{
    public function __construct(private readonly RevenueProductTitleBuilder $titleBuilder) {}

    /**
     * Applies a client-submitted title: null (blank) or equal to the automatic
     * title keeps the commessa automatic; any other value is manual. Called
     * after the lines are written, so the comparison sees them.
     */
    public function write(WorkOrder $workOrder, ?string $submittedTitle): void
    {
        $submittedTitle = $submittedTitle === null || trim($submittedTitle) === '' ? null : $submittedTitle;
        $derivedTitle = $this->titleBuilder->forWorkOrder($workOrder);
        $isManual = $submittedTitle !== null && $submittedTitle !== $derivedTitle;

        $workOrder->forceFill([
            'title' => $isManual ? $submittedTitle : $derivedTitle,
            'title_is_manual' => $isManual,
        ])->save();
    }

    /** Re-derives the automatic title; a manual title is left untouched. */
    public function recalculate(WorkOrder $workOrder): void
    {
        if ($workOrder->title_is_manual) {
            return;
        }

        $workOrder->forceFill(['title' => $this->titleBuilder->forWorkOrder($workOrder)])->save();
    }
}
