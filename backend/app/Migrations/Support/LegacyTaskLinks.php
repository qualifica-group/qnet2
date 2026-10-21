<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Task;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskReferentRegistryGuard;
use App\Services\Tasks\TaskWorkOrderOpportunityGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The record links of an imported task (spec 0289, D-7, D-9): parent, commessa,
 * stage, client, referent and opportunity, resolved through `old_id`. The
 * commessa not migrated fails the row; a parent, client, referent or
 * opportunity not migrated degrades to null with a warning. The client/referent
 * coherence reuses the task module's own guards instead of restating the rules.
 */
final class LegacyTaskLinks
{
    public function __construct(
        private readonly LegacyTaskLookups $lookups,
        private readonly TaskWorkOrderOpportunityGuard $workOrderGuard,
        private readonly TaskReferentRegistryGuard $referentGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array{parent_task_id: ?int, work_order_id: ?int, work_order_stage_id: ?int, registry_id: ?int, referent_id: ?int, opportunity_id: ?int}
     *
     * @throws RuntimeException when the commessa is sent but not migrated
     */
    public function resolve(array $record, array &$warnings): array
    {
        $parentId = $this->remap(Task::class, $record['parent_id'] ?? null, 'Parent task', 'imported as a root task', $warnings);
        $workOrderId = $this->workOrderId($record['work_order_id'] ?? null);
        $registryId = $this->registryId($record, $workOrderId, $warnings);

        return [
            'parent_task_id' => $parentId,
            'work_order_id' => $workOrderId,
            'work_order_stage_id' => $this->stageId($workOrderId, $parentId, $record['work_order_stage_name'] ?? null, $warnings),
            'registry_id' => $registryId,
            'referent_id' => $this->referentId($record['referent_id'] ?? null, $registryId, $warnings),
            'opportunity_id' => $this->remap(Opportunity::class, $record['opportunity_id'] ?? null, 'Opportunity', 'left empty', $warnings),
        ];
    }

    private function workOrderId(mixed $legacyWorkOrderId): ?int
    {
        if ($this->isBlank($legacyWorkOrderId)) {
            return null;
        }

        $workOrderId = $this->oldId(WorkOrder::class, $legacyWorkOrderId);

        if ($workOrderId === null) {
            throw new RuntimeException("Work order (legacy id {$legacyWorkOrderId}) not migrated; the task is not imported.");
        }

        return $workOrderId;
    }

    /**
     * The legacy client, or the one the commessa imposes: a legacy client
     * that is not among the commessa's gets replaced by it (D-9).
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function registryId(array $record, ?int $workOrderId, array &$warnings): ?int
    {
        $registryId = $this->remap(Registry::class, $record['registry_id'] ?? null, 'Registry', 'left empty', $warnings);

        try {
            return $this->workOrderGuard->resolveRegistryId($registryId, $workOrderId);
        } catch (ValidationException) {
            $warnings[] = "Registry (legacy id {$record['registry_id']}) is not a client of the work order; the work order's client was used.";

            return $this->workOrderGuard->resolveRegistryId(null, $workOrderId);
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function referentId(mixed $legacyReferentId, ?int $registryId, array &$warnings): ?int
    {
        $referentId = $this->remap(Referent::class, $legacyReferentId, 'Referent', 'left empty', $warnings);

        if ($referentId === null) {
            return null;
        }

        try {
            $this->referentGuard->assertBelongs($registryId, $referentId);
        } catch (ValidationException) {
            $warnings[] = "Referent (legacy id {$legacyReferentId}) does not belong to the task's registry; left empty.";

            return null;
        }

        return $referentId;
    }

    /**
     * The stage by name among the commessa's, on root tasks only.
     *
     * @param  array<int, string>  $warnings
     */
    private function stageId(?int $workOrderId, ?int $parentId, mixed $stageName, array &$warnings): ?int
    {
        $stageName = trim((string) ($stageName ?? ''));

        if ($workOrderId === null || $parentId !== null || $stageName === '') {
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
    private function remap(string $modelClass, mixed $legacyId, string $what, string $outcome, array &$warnings): ?int
    {
        if ($this->isBlank($legacyId)) {
            return null;
        }

        $id = $this->oldId($modelClass, $legacyId);

        if ($id === null) {
            $warnings[] = "{$what} (legacy id {$legacyId}) not migrated; {$outcome}.";
        }

        return $id;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function oldId(string $modelClass, mixed $legacyId): ?int
    {
        $id = $modelClass::query()->where('old_id', $legacyId)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function isBlank(mixed $legacyId): bool
    {
        return $legacyId === null || $legacyId === '' || (int) $legacyId === 0;
    }
}
