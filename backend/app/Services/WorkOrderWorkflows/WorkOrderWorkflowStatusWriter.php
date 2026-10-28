<?php

declare(strict_types=1);

namespace App\Services\WorkOrderWorkflows;

use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `work_order_workflow_statuses` write-side, SCOPED per set (a workflow's own
 * `work_order_workflow_id`, or the GLOBAL default set when null) — the twin of
 * App\Services\QuoteWorkflows\WorkflowStatusWriter (spec 0322, D-6), not
 * shared with it because the system keys and the group enum differ. Every set
 * independently pins its OWN system rows: 'open' first, 'completed' and
 * 'closed' last (D-2).
 *
 * `work_order_workflow_id`/`system_key` are DELIBERATELY absent from
 * WorkOrderWorkflowStatus's #[Fillable], so every write touching either goes
 * through forceCreate(); the request-payload fields (name/description/color/
 * progress_percentage/group) stay on the guarded fill() path.
 *
 * A system row accepts name, description, color and progress_percentage only:
 * a submitted `group` for it is ignored, the group stays equal to its key.
 */
final class WorkOrderWorkflowStatusWriter
{
    private const int STEP = 10;

    /**
     * Creates a brand-new set: the pinned 'open' row (sort_order 0), every
     * $customStatuses row in submission order (STEP apart), then the pinned
     * tail rows 'completed' and 'closed' (always last, in that order). The
     * overrides seed the pinned rows' descriptive fields when the client
     * filled them up front; null falls back to the default label.
     *
     * @param  array<int, array{name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $customStatuses
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $openOverride
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $completedOverride
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $closedOverride
     */
    public function createWithCustoms(
        ?int $workflowId,
        array $customStatuses,
        ?array $openOverride = null,
        ?array $completedOverride = null,
        ?array $closedOverride = null,
    ): void {
        $this->forceCreateSystemRow($workflowId, WorkOrderWorkflowStatusSystemKey::Open, 0, $openOverride);

        $sortOrder = self::STEP;

        foreach ($customStatuses as $status) {
            $this->forceCreateCustomRow($workflowId, $status, $sortOrder);
            $sortOrder += self::STEP;
        }

        $tailOverrides = [
            WorkOrderWorkflowStatusSystemKey::Completed->value => $completedOverride,
            WorkOrderWorkflowStatusSystemKey::Closed->value => $closedOverride,
        ];

        foreach (WorkOrderWorkflowStatusSystemKey::tailKeys() as $key) {
            $this->forceCreateSystemRow($workflowId, $key, $sortOrder, $tailOverrides[$key->value]);
            $sortOrder += self::STEP;
        }
    }

    /**
     * Authoritative sync of the set's CUSTOM rows (id present = update,
     * absent = new; existing customs not included = deleted), resequencing
     * sort_order so 'open' stays first and 'completed'/'closed' stay last. A
     * submitted row whose `id` matches an existing SYSTEM row only updates its
     * descriptive fields and is never counted as a custom nor deleted.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $statusRows
     */
    public function syncCustoms(?int $workflowId, array $statusRows): void
    {
        DB::transaction(function () use ($workflowId, $statusRows): void {
            // Step 1: the set as it stands.
            $existing = WorkOrderWorkflowStatus::query()
                ->where('work_order_workflow_id', $workflowId)
                ->get()
                ->keyBy('id');

            // Step 2: the payload splits into system updates + custom sync.
            [$systemRows, $customRows] = $this->partitionSubmitted($statusRows, $existing);

            $this->applySystemUpdates($systemRows, $existing);
            $orderedCustomIds = $this->applyCustomSync($workflowId, $customRows, $existing);
            $this->resequence($workflowId, $orderedCustomIds);
        });
    }

    /**
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}  $row
     * @return array<string, mixed>
     */
    private function descriptiveAttributes(array $row): array
    {
        return [
            'name' => $row['name'],
            'description' => $row['description'],
            'color' => $row['color'],
            'progress_percentage' => $row['progress_percentage'],
        ];
    }

    /**
     * @param  array{name: string, description: ?string, color: ?string, progress_percentage: ?int}|null  $override
     */
    private function forceCreateSystemRow(?int $workflowId, WorkOrderWorkflowStatusSystemKey $key, int $sortOrder, ?array $override = null): void
    {
        $defaultName = match ($key) {
            WorkOrderWorkflowStatusSystemKey::Open => 'Aperta',
            WorkOrderWorkflowStatusSystemKey::Completed => 'Completata',
            WorkOrderWorkflowStatusSystemKey::Closed => 'Chiusa',
        };

        WorkOrderWorkflowStatus::query()->forceCreate([
            'work_order_workflow_id' => $workflowId,
            'name' => $override['name'] ?? $defaultName,
            'description' => $override['description'] ?? null,
            'color' => $override['color'] ?? null,
            'progress_percentage' => $override['progress_percentage'] ?? null,
            'sort_order' => $sortOrder,
            'system_key' => $key->value,
            'group' => $key->group(),
        ]);
    }

    /**
     * @param  array{name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}  $status
     */
    private function forceCreateCustomRow(?int $workflowId, array $status, int $sortOrder): void
    {
        WorkOrderWorkflowStatus::query()->forceCreate([
            'work_order_workflow_id' => $workflowId,
            ...$this->descriptiveAttributes($status),
            'sort_order' => $sortOrder,
            'system_key' => null,
            'group' => $status['group'],
        ]);
    }

    /**
     * Splits $statusRows into [systemRows, customRows] by whether a submitted
     * `id` resolves to an existing SYSTEM row in $existing.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, progress_percentage: ?int}>  $statusRows
     * @param  Collection<int, WorkOrderWorkflowStatus>  $existing
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function partitionSubmitted(array $statusRows, Collection $existing): array
    {
        $systemRows = [];
        $customRows = [];

        foreach ($statusRows as $row) {
            $existingRow = $row['id'] !== null ? $existing->get($row['id']) : null;

            if ($existingRow !== null && $existingRow->isSystem()) {
                $systemRows[] = $row;
            } else {
                $customRows[] = $row;
            }
        }

        return [$systemRows, $customRows];
    }

    /**
     * @param  array<int, array<string, mixed>>  $systemRows
     * @param  Collection<int, WorkOrderWorkflowStatus>  $existing
     */
    private function applySystemUpdates(array $systemRows, Collection $existing): void
    {
        foreach ($systemRows as $row) {
            $existing->get($row['id'])->fill($this->descriptiveAttributes($row))->save();
        }
    }

    /**
     * Updates/creates every submitted custom row (in submission order),
     * deletes the customs left out, and returns the kept ids IN ORDER — the
     * sequence resequence() places between 'open' and the tail.
     *
     * @param  array<int, array<string, mixed>>  $customRows
     * @param  Collection<int, WorkOrderWorkflowStatus>  $existing
     * @return array<int, int>
     */
    private function applyCustomSync(?int $workflowId, array $customRows, Collection $existing): array
    {
        $keptIds = [];

        foreach ($customRows as $row) {
            if ($row['id'] === null) {
                $created = WorkOrderWorkflowStatus::query()->forceCreate([
                    'work_order_workflow_id' => $workflowId,
                    ...$this->descriptiveAttributes($row),
                    'sort_order' => 0,
                    'system_key' => null,
                    'group' => $row['group'],
                ]);

                $keptIds[] = $created->id;

                continue;
            }

            $status = $existing->get($row['id']);

            if ($status === null || $status->isSystem()) {
                abort(422, "Unknown custom status id [{$row['id']}] for this workflow.");
            }

            $status->fill([...$this->descriptiveAttributes($row), 'group' => $row['group']])->save();
            $keptIds[] = $status->id;
        }

        $existing
            ->filter(static fn (WorkOrderWorkflowStatus $status): bool => ! $status->isSystem() && ! in_array($status->id, $keptIds, true))
            ->each(static fn (WorkOrderWorkflowStatus $status) => $status->delete());

        return $keptIds;
    }

    /**
     * @param  array<int, int>  $orderedCustomIds
     */
    private function resequence(?int $workflowId, array $orderedCustomIds): void
    {
        WorkOrderWorkflowStatus::query()
            ->where('work_order_workflow_id', $workflowId)
            ->where('system_key', WorkOrderWorkflowStatusSystemKey::Open->value)
            ->update(['sort_order' => 0]);

        $sortOrder = self::STEP;

        foreach ($orderedCustomIds as $id) {
            WorkOrderWorkflowStatus::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }

        foreach (WorkOrderWorkflowStatusSystemKey::tailKeys() as $key) {
            WorkOrderWorkflowStatus::query()
                ->where('work_order_workflow_id', $workflowId)
                ->where('system_key', $key->value)
                ->update(['sort_order' => $sortOrder]);

            $sortOrder += self::STEP;
        }
    }
}
