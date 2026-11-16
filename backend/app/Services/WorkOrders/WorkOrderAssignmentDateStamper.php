<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\FormMode;
use App\Models\WorkOrder;
use App\Services\Notifications\AssignmentNotifier;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Support\Carbon;

/**
 * Sets the "Data di assegnazione" of a commessa to today when a Responsabile
 * is added to it (task 428419), as the legacy did on every new technician.
 * The codes come from `work-orders.assignment_date_attribute_codes`; only the
 * ones the commessa's own categories carry are written, through the same
 * attribute pipeline the form uses.
 */
final class WorkOrderAssignmentDateStamper
{
    public function __construct(
        private readonly WorkOrderAttributeResolver $attributeResolver,
        private readonly WorkOrderAttributeValueWriter $attributeValueWriter,
    ) {}

    /**
     * @param  array<int, int>  $addedSupervisorIds  the Responsabili this save just added
     * @param  array<string, mixed>  $valuesBefore  the attribute values before this save
     */
    public function stamp(WorkOrder $workOrder, array $addedSupervisorIds, array $valuesBefore, FormMode $formMode): void
    {
        // Step 1: only a real assignment; a system write (legacy migration) keeps its own values
        if ($addedSupervisorIds === [] || AssignmentNotifier::isMuted()) {
            return;
        }

        // Step 2: the configured codes of this commessa that the same save did not set by hand
        $codes = $this->stampableCodes($workOrder, $valuesBefore);

        if ($codes === []) {
            return;
        }

        // Step 3: write through the form's pipeline (validation, hidden sections, row notifications)
        $changed = [];
        $old = [];
        $today = Carbon::now(config('work-orders.assignment_date_timezone'))->toDateString();
        $this->attributeValueWriter->apply(
            $workOrder,
            array_fill_keys($codes, $today),
            $changed,
            $old,
            layout: $this->attributeResolver->layout($workOrder, $formMode),
        );
        $workOrder->save();
    }

    /**
     * @param  array<string, mixed>  $valuesBefore
     * @return array<int, string>
     */
    private function stampableCodes(WorkOrder $workOrder, array $valuesBefore): array
    {
        $applicable = $this->attributeResolver->resolve($workOrder)->pluck('code')->all();
        $current = $workOrder->attribute_values ?? [];

        return array_values(array_filter(
            array_intersect((array) config('work-orders.assignment_date_attribute_codes'), $applicable),
            static fn (string $code): bool => ($current[$code] ?? null) === ($valuesBefore[$code] ?? null),
        ));
    }
}
