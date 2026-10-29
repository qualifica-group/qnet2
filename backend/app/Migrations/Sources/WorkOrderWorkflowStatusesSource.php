<?php

namespace App\Migrations\Sources;

use App\Enums\WorkOrderStatus;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * `work-order-workflow-statuses` migration source (spec 0322, D-12): the current
 * working status of each migrated work order. The legacy sends one record per
 * work order (`id` = orders.id, `status_id` = progress.id), already reduced to
 * the most recent valid status, so here both are only looked up.
 *
 * The status is written ONLY when it belongs to the set the resolver gives the
 * work order (its workflow, or the global one); otherwise the work order stays
 * on the status it has and the row returns a warning. A work order or status
 * not migrated fails the row. Written quietly, without activity log; a work
 * order already on that status is skipped.
 *
 * A force-closed work order follows the outcome the legacy sends in
 * `closing_group` (spec 0324, D-10): "completed" (Chiusa) keeps a legacy
 * status of the completed or closed group, otherwise lands on the set's
 * `completed` row; "closed" (Annullata/Disdetta) keeps a legacy status of the
 * closed group, otherwise lands on the set's `closed` row. Without an outcome
 * it keeps its closing status unless the legacy status is of a closing group
 * (D-5). Closed orders without a legacy status arrive with `status_id` null.
 */
class WorkOrderWorkflowStatusesSource extends AbstractMigrationSource
{
    private const array CLOSING_GROUPS = [WorkOrderStatus::Completed, WorkOrderStatus::Closed];

    /** The legacy statuses a force-closed work order keeps, per legacy outcome (D-10). */
    private const array KEPT_GROUPS_BY_OUTCOME = [
        'completed' => [WorkOrderStatus::Completed, WorkOrderStatus::Closed],
        'closed' => [WorkOrderStatus::Closed],
    ];

    private ?WorkOrderWorkflowResolver $resolver = null;

    public function key(): string
    {
        return 'work-order-workflow-statuses';
    }

    public function label(): string
    {
        return 'Work order workflow statuses';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('work-orders'),
            MigrationDependency::required('work-order-workflows'),
        ];
    }

    public function endpoint(): string
    {
        return 'work-order-workflow-statuses';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'status_id', 'label' => 'Status (external id)', 'type' => 'number'],
            ['id' => 'closing_group', 'label' => 'Closing outcome', 'type' => 'string'],
        ];
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'status_id' => $record['status_id'] ?? null,
            'closing_group' => $record['closing_group'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the work order and the legacy status (when sent) must already be migrated
        $workOrder = WorkOrder::query()->with('quoteLines.product')->where('old_id', $externalId)->first()
            ?? throw new RuntimeException("Work order not migrated (legacy id {$externalId}); migrate work orders first.");
        $status = $this->legacyStatus($record['status_id'] ?? null);

        // Step 2: the set resolved for this work order
        $resolver = $this->resolver ??= app(WorkOrderWorkflowResolver::class);
        $statuses = $resolver->statusesFor($resolver->resolve($workOrder));
        $outcome = $workOrder->is_force_closed ? $this->closingOutcome($record, $warnings) : null;

        // Step 3: the target status, or a skip with its reason
        $target = $outcome !== null
            ? $this->closingTarget($statuses, $status, $outcome, $warnings)
            : $this->plainTarget($workOrder, $statuses, $status, $warnings);

        if ($target === null || $workOrder->work_order_workflow_status_id === $target->id) {
            return MigrationRowOutcome::skipped($warnings);
        }

        // Step 4: quiet write, no activity log
        activity()->withoutLogs(fn () => $workOrder->forceFill(['work_order_workflow_status_id' => $target->id])->saveQuietly());

        return MigrationRowOutcome::created($warnings);
    }

    private function legacyStatus(mixed $statusId): ?WorkOrderWorkflowStatus
    {
        if ($statusId === null) {
            return null;
        }

        return WorkOrderWorkflowStatus::query()->where('old_id', $statusId)->first()
            ?? throw new RuntimeException("Workflow status not migrated (legacy id {$statusId}); migrate work order workflows first.");
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<string>  $warnings
     */
    private function closingOutcome(array $record, array &$warnings): ?string
    {
        $outcome = $record['closing_group'] ?? null;

        if ($outcome !== null && ! array_key_exists($outcome, self::KEPT_GROUPS_BY_OUTCOME)) {
            $warnings[] = "Unknown closing_group \"{$outcome}\": ignored.";

            return null;
        }

        return $outcome;
    }

    /**
     * D-10: the legacy status when its group fits the legacy outcome, else the
     * set's system row of that outcome.
     *
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     * @param  list<string>  $warnings
     */
    private function closingTarget(Collection $statuses, ?WorkOrderWorkflowStatus $status, string $outcome, array &$warnings): ?WorkOrderWorkflowStatus
    {
        if ($status !== null && $statuses->contains('id', $status->id) && in_array($status->group, self::KEPT_GROUPS_BY_OUTCOME[$outcome], true)) {
            return $status;
        }

        $target = $statuses->firstWhere('system_key', $outcome);

        if ($status !== null && $target !== null) {
            $warnings[] = "Work order is force-closed with outcome \"{$outcome}\": \"{$target->name}\" instead of \"{$status->name}\".";
        }

        return $target;
    }

    /**
     * Records without a legacy outcome: a force-closed work order keeps its
     * closing status unless the legacy status is of a closing group (D-5);
     * only a status of the resolved set is written.
     *
     * @param  Collection<int, WorkOrderWorkflowStatus>  $statuses
     * @param  list<string>  $warnings
     */
    private function plainTarget(WorkOrder $workOrder, Collection $statuses, ?WorkOrderWorkflowStatus $status, array &$warnings): ?WorkOrderWorkflowStatus
    {
        if ($status === null) {
            return null;
        }

        if ($workOrder->is_force_closed && ! in_array($status->group, self::CLOSING_GROUPS, true)) {
            $warnings[] = "Work order is force-closed: it keeps its closing status instead of \"{$status->name}\".";

            return null;
        }

        if (! $statuses->contains('id', $status->id)) {
            $warnings[] = "Status \"{$status->name}\" is not in the workflow resolved for this work order; left unchanged.";

            return null;
        }

        return $status;
    }
}
