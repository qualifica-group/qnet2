<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\WorkOrder;
use App\Services\Quotes\RevenueProductTitleBuilder;
use App\Support\ManagerPositions;
use App\Support\PositionalPivotSync;
use Carbon\CarbonImmutable;

/**
 * Realigns an already imported commessa with the legacy (spec 0372, D-5): the
 * caller has already checked that it was not touched and that its people are
 * migrated, and hands over the mapped legacy values. Only differences are
 * written, quietly (no task closing, no notification, no activity log: the
 * caller runs muted and without log) and the commessa's `updated_at` becomes
 * the legacy one. `code`, `quote_id`, `type`, lines, costs, task template and
 * workflow status are never touched (D-9, D-13).
 */
final class LegacyWorkOrderRealigner
{
    public function __construct(
        private readonly RevenueProductTitleBuilder $titleBuilder,
        private readonly LegacyAttributeValueApplier $attributeValueApplier,
        private readonly LegacyWorkOrderStageWriter $stageWriter,
        private readonly LegacyWorkOrderLinePaymentWriter $linePaymentWriter,
    ) {}

    /**
     * @param  array{title: string, start_date: string, callback_date: ?string, description: ?string, internal_notes: ?string, force_close_reason: ?string, supervisor_ids: array<int, int>, participant_slots: array<int, int|null>}  $values  the mapped legacy values
     * @param  array<string, mixed>  $record  the legacy commessa
     * @param  array<int, string>  $warnings
     * @return bool true only when something was written
     */
    public function handle(WorkOrder $workOrder, array $values, array $record, ?CarbonImmutable $updatedAt, array &$warnings): bool
    {
        // Step 1: the scalar fields and the flexible fields, merged in memory.
        $workOrder->forceFill($this->scalarAttributes($workOrder, $values));
        $this->attributeValueApplier->applyToWorkOrder($workOrder, (array) ($record['attribute_values'] ?? []), $warnings);

        // Step 2: people, phases and payment data, each written only when different.
        $changed = $this->syncSupervisors($workOrder, $values['supervisor_ids']);
        $changed = $this->syncParticipants($workOrder, $values['participant_slots']) || $changed;
        $changed = $this->stageWriter->sync($workOrder, (array) ($record['stages'] ?? []), $warnings) || $changed;
        $changed = $this->linePaymentWriter->sync($workOrder, $record, $warnings) || $changed;
        $changed = $workOrder->isDirty() || $changed;

        // Step 3: one quiet save carrying the legacy `updated_at`.
        if ($changed) {
            $workOrder->forceFill($updatedAt !== null ? ['updated_at' => $updatedAt] : [])->saveQuietly();
        }

        return $changed;
    }

    /**
     * The title follows the creation rule (WorkOrderTitleWriter::write): the
     * legacy title equal to the automatic one stays automatic, any other is
     * manual; a closed-less commessa keeps `force_close_reason` null (the
     * invariant of WorkOrderService).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scalarAttributes(WorkOrder $workOrder, array $values): array
    {
        $derivedTitle = $this->titleBuilder->forWorkOrder($workOrder);
        $isManual = $values['title'] !== '' && $values['title'] !== $derivedTitle;

        return [
            'title' => $isManual ? $values['title'] : $derivedTitle,
            'title_is_manual' => $isManual,
            'start_date' => $values['start_date'],
            'callback_date' => $values['callback_date'],
            'description' => $values['description'],
            'internal_notes' => $values['internal_notes'],
            'is_force_closed' => $values['force_close_reason'] !== null,
            'force_close_reason' => $values['force_close_reason'],
        ];
    }

    /**
     * @param  array<int, int>  $supervisorIds
     */
    private function syncSupervisors(WorkOrder $workOrder, array $supervisorIds): bool
    {
        $current = $workOrder->supervisors()->pluck('users.id')->map(intval(...))->sort()->values()->all();
        $target = collect($supervisorIds)->map(intval(...))->sort()->values()->all();

        if ($current === $target) {
            return false;
        }

        $workOrder->supervisors()->sync($supervisorIds);

        return true;
    }

    /**
     * @param  array<int, int|null>  $slots
     */
    private function syncParticipants(WorkOrder $workOrder, array $slots): bool
    {
        $target = [];

        foreach (ManagerPositions::syncMap($slots) as $userId => $pivot) {
            $target[(int) $userId] = $pivot['position'];
        }

        $current = ManagerPositions::positionsOf($workOrder->participants()->get());
        ksort($current);
        ksort($target);

        if ($current === $target) {
            return false;
        }

        PositionalPivotSync::sync($workOrder->participants(), ManagerPositions::syncMap($slots));

        return true;
    }
}
