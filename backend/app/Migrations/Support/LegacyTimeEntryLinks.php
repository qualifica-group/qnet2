<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskEffectiveStage;
use Illuminate\Database\Eloquent\Model;

/**
 * The record links of an imported segnatempo (spec 0341 D-5), qnet-2's own
 * link rule applied to history: a migrated Task imposes title, client,
 * opportunity, commessa and stage (as TimeEntryLinkResolver does, without the
 * visibility check: the entry is history, not a user's write). Without a Task
 * the commessa wins over an opportunity, the record's client wins over the
 * legacy one, and the stage is matched by name among the commessa's stages.
 * Every link not migrated degrades to null with a warning (U-4).
 */
final class LegacyTimeEntryLinks
{
    public function __construct(private readonly LegacyTaskLookups $lookups) {}

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{title: ?string, registry_id: ?int, opportunity_id: ?int, work_order_id: ?int, task_id: ?int, work_order_stage_id: ?int}
     */
    public function resolve(array $record, array &$warnings): array
    {
        $taskId = $this->remap(Task::class, $record['task_id'] ?? null, 'Task', $warnings);

        if ($taskId !== null) {
            return $this->fromTask(Task::query()->findOrFail($taskId));
        }

        return $this->standalone($record, $warnings);
    }

    /**
     * @return array{title: string, registry_id: ?int, opportunity_id: ?int, work_order_id: ?int, task_id: int, work_order_stage_id: ?int}
     */
    private function fromTask(Task $task): array
    {
        return [
            'title' => $task->title,
            'registry_id' => $task->registry_id,
            'opportunity_id' => $task->opportunity_id,
            'work_order_id' => $task->work_order_id,
            'task_id' => $task->id,
            'work_order_stage_id' => TaskEffectiveStage::forTask($task),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{title: null, registry_id: ?int, opportunity_id: ?int, work_order_id: ?int, task_id: null, work_order_stage_id: ?int}
     */
    private function standalone(array $record, array &$warnings): array
    {
        $workOrderId = $this->remap(WorkOrder::class, $record['work_order_id'] ?? null, 'Work order', $warnings);
        $opportunityId = $this->remap(Opportunity::class, $record['opportunity_id'] ?? null, 'Opportunity', $warnings);

        if ($workOrderId !== null && $opportunityId !== null) {
            $warnings[] = 'Both a work order and an opportunity were sent; the opportunity was dropped.';
            $opportunityId = null;
        }

        return [
            'title' => null,
            'registry_id' => $this->registryId($record['registry_id'] ?? null, $opportunityId, $workOrderId, $warnings),
            'opportunity_id' => $opportunityId,
            'work_order_id' => $workOrderId,
            'task_id' => null,
            'work_order_stage_id' => $this->stageId($workOrderId, $record['stage_name'] ?? null, $warnings),
        ];
    }

    /**
     * The client of the linked record, else the legacy registry.
     *
     * @param  array<int, string>  $warnings
     */
    private function registryId(mixed $legacyRegistryId, ?int $opportunityId, ?int $workOrderId, array &$warnings): ?int
    {
        $registryId = $this->remap(Registry::class, $legacyRegistryId, 'Registry', $warnings);
        $clientId = match (true) {
            $opportunityId !== null => Opportunity::query()->whereKey($opportunityId)->value('registry_id'),
            $workOrderId !== null => WorkOrder::query()->with('quote.opportunity')->find($workOrderId)?->quote?->opportunity?->registry_id,
            default => null,
        };

        if ($clientId === null) {
            return $registryId;
        }

        if ($registryId !== null && $registryId !== (int) $clientId) {
            $warnings[] = "Registry (legacy id {$legacyRegistryId}) is not the client of the linked record; the record's client was used.";
        }

        return (int) $clientId;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function stageId(?int $workOrderId, mixed $stageName, array &$warnings): ?int
    {
        $stageName = trim((string) ($stageName ?? ''));

        if ($workOrderId === null || $stageName === '') {
            return null;
        }

        $stageId = $this->lookups->stageIdByName($workOrderId, $stageName);

        if ($stageId === null) {
            $warnings[] = "Stage \"{$stageName}\" not found on the work order; imported without stage.";
        }

        return $stageId;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $warnings
     */
    private function remap(string $modelClass, mixed $legacyId, string $what, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId === 0) {
            return null;
        }

        $id = $modelClass::query()->where('old_id', $legacyId)->value('id');

        if ($id === null) {
            $warnings[] = "{$what} (legacy id {$legacyId}) not migrated; imported without it.";

            return null;
        }

        return (int) $id;
    }
}
