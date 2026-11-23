<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\WorkOrder;
use App\WorkOrders\WorkOrderAttributeLocks;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Contracts\Validation\Validator;

/**
 * Refuses (422 on `attribute_values.<code>`) a change to a work order
 * attribute owned by a registered WorkOrderAttributeLock.
 */
trait RejectsLockedWorkOrderAttributes
{
    /** Update: the locks of the stored work order, against its stored values. */
    protected function rejectLockedAttributeChangesOf(Validator $validator, WorkOrder $workOrder): void
    {
        $this->rejectLockedAttributeChanges($validator, app(WorkOrderAttributeResolver::class)->lockedCodes($workOrder), $workOrder->attribute_values ?? []);
    }

    /** Create: the locks of the submitted `quote_line_ids`; nothing is stored yet. */
    protected function rejectLockedAttributeChangesOnCreate(Validator $validator): void
    {
        $lineIds = array_map(intval(...), array_filter((array) $this->input('quote_line_ids', []), is_numeric(...)));

        $this->rejectLockedAttributeChanges($validator, app(WorkOrderAttributeResolver::class)->lockedCodesForQuoteLines(array_values($lineIds)), []);
    }

    /**
     * @param  list<string>  $lockedCodes
     * @param  array<string, mixed>  $current
     */
    private function rejectLockedAttributeChanges(Validator $validator, array $lockedCodes, array $current): void
    {
        $submitted = $this->input('attribute_values');

        if ($lockedCodes === [] || ! is_array($submitted)) {
            return;
        }

        foreach (app(WorkOrderAttributeLocks::class)->changedCodes($lockedCodes, $current, $submitted) as $code) {
            $validator->errors()->add("attribute_values.{$code}", __('work_orders.attribute_locked'));
        }
    }
}
