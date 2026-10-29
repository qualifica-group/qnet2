<?php

namespace App\Migrations\Sources;

use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Models\TaskTemplate;
use App\Models\WorkOrder;
use RuntimeException;

/**
 * `work-order-task-templates` migration source (spec 0326): the task template
 * each migrated work order was built from. The legacy sends one record per
 * work order (`id` = orders.id, `task_template_id` = templatebtypes.id),
 * already inferred from the generated tasks (D-2), so here both are only
 * looked up.
 *
 * The link is written quietly, without activity log and outside
 * WorkOrderService, so no task is generated (D-4). A work order or template
 * not migrated fails the row; a work order already on that template is
 * skipped; one on another template (chosen in qnet-2) is left as is with a
 * warning (D-5).
 */
class WorkOrderTaskTemplatesSource extends AbstractMigrationSource
{
    public function key(): string
    {
        return 'work-order-task-templates';
    }

    public function label(): string
    {
        return 'Work order task templates';
    }

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('work-orders'),
            MigrationDependency::required('task-templates'),
        ];
    }

    public function endpoint(): string
    {
        return 'work-order-task-templates';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'Work order (external id)', 'type' => 'number'],
            ['id' => 'task_template_id', 'label' => 'Task template (external id)', 'type' => 'number'],
            ['id' => 'matched_tasks', 'label' => 'Matched tasks', 'type' => 'number'],
            ['id' => 'template_actions', 'label' => 'Template actions', 'type' => 'number'],
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
            'task_template_id' => $record['task_template_id'] ?? null,
            'matched_tasks' => $record['matched_tasks'] ?? null,
            'template_actions' => $record['template_actions'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        $warnings = array_map('strval', (array) ($record['warnings'] ?? []));

        // Step 1: the work order and the template must already be migrated
        $workOrder = WorkOrder::query()->where('old_id', $externalId)->first()
            ?? throw new RuntimeException("Work order not migrated (legacy id {$externalId}); migrate work orders first.");
        $templateId = $this->legacyTemplateId($record['task_template_id'] ?? null);

        // Step 2: a template already set is never replaced
        if ($workOrder->task_template_id === $templateId) {
            return MigrationRowOutcome::skipped($warnings);
        }

        if ($workOrder->task_template_id !== null) {
            $warnings[] = 'Work order already has another task template; left unchanged.';

            return MigrationRowOutcome::skipped($warnings);
        }

        // Step 3: quiet write, no activity log, no task generation
        activity()->withoutLogs(fn () => $workOrder->forceFill(['task_template_id' => $templateId])->saveQuietly());

        return MigrationRowOutcome::created($warnings);
    }

    private function legacyTemplateId(mixed $externalTemplateId): int
    {
        $templateId = ($externalTemplateId === null || $externalTemplateId === '')
            ? null
            : $this->resolveOldId(TaskTemplate::class, $externalTemplateId);

        return $templateId
            ?? throw new RuntimeException("Task template not migrated (legacy id {$externalTemplateId}); migrate task templates first.");
    }
}
