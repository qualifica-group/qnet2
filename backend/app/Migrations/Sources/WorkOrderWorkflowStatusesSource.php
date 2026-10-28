<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
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
 */
class WorkOrderWorkflowStatusesSource extends AbstractMigrationSource
{
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
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the work order and the status must already be migrated
        $workOrder = WorkOrder::query()->with('quoteLines.product')->where('old_id', $externalId)->first()
            ?? throw new RuntimeException("Work order not migrated (legacy id {$externalId}); migrate work orders first.");

        $status = WorkOrderWorkflowStatus::query()->where('old_id', $record['status_id'] ?? null)->first()
            ?? throw new RuntimeException('Workflow status not migrated (legacy id '.($record['status_id'] ?? 'null').'); migrate work order workflows first.');

        // Step 2: only a status of the set resolved for this work order is written
        $resolver = $this->resolver ??= app(WorkOrderWorkflowResolver::class);
        $allowedIds = $resolver->statusesFor($resolver->resolve($workOrder))->pluck('id');

        if (! $allowedIds->contains($status->id)) {
            return MigrationRowOutcome::skipped([...$warnings, "Status \"{$status->name}\" is not in the workflow resolved for this work order; left unchanged."]);
        }

        // Step 3: nothing to do when it is already the current status
        if ($workOrder->work_order_workflow_status_id === $status->id) {
            return MigrationRowOutcome::skipped($warnings);
        }

        // Step 4: quiet write, no activity log
        activity()->withoutLogs(fn () => $workOrder->forceFill(['work_order_workflow_status_id' => $status->id])->saveQuietly());

        return MigrationRowOutcome::created($warnings);
    }
}
