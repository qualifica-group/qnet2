<?php

declare(strict_types=1);

namespace App\Services\OpportunityWorkflows;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\OpportunityWorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `opportunity_workflow_statuses` write-side, SCOPED per set (a workflow's
 * own `opportunity_workflow_id`, or the GLOBAL default set when null) —
 * modeled on App\Services\Statuses\SystemStatusGuard +
 * App\Services\Statuses\StatusOrderManager (spec 0039/0043), NOT reused
 * directly: those two are hardcoded to the PipelineStatus/OpportunityStatus
 * shape (a single global set, StatusSystemKey::New tail-anchoring), whereas
 * every OpportunityWorkflowStatus set independently pins its OWN system rows —
 * the initial 'open' row plus the two terminal closed-outcome rows
 * 'closed_won'/'closed_lost' (AC-004/AC-005).
 *
 * `opportunity_workflow_id`/`system_key` are DELIBERATELY absent from
 * OpportunityWorkflowStatus's #[Fillable] (never mass-assignable from a
 * request), so every write here that touches either goes through
 * forceFill()/forceCreate() — the request-payload fields (name/color/group)
 * remain on the normal, guarded fill() path.
 */
final class WorkflowStatusWriter
{
    private const int STEP = 10;

    /**
     * Creates a brand-new set (AC-004): the pinned 'open' row (sort_order 0),
     * every $customStatuses row in submission order (STEP apart), then the
     * pinned tail rows 'validated'/'closed_won'/'closed_lost' (always last, in
     * that order). $openOverride/$validatedOverride/$closedWonOverride/
     * $closedLostOverride seed the pinned rows' descriptive fields when the
     * client filled them up front; null falls back to the default label.
     *
     * @param  array<int, array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $customStatuses
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $openOverride
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $validatedOverride
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $closedWonOverride
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $closedLostOverride
     */
    public function createWithCustoms(
        ?int $workflowId,
        array $customStatuses,
        ?array $openOverride = null,
        ?array $validatedOverride = null,
        ?array $closedWonOverride = null,
        ?array $closedLostOverride = null,
    ): void {
        $this->forceCreateSystemRow($workflowId, WorkflowStatusSystemKey::Open, 0, $openOverride);

        $sortOrder = self::STEP;

        foreach ($customStatuses as $status) {
            $this->forceCreateCustomRow($workflowId, $status, $sortOrder);
            $sortOrder += self::STEP;
        }

        $tailOverrides = [
            WorkflowStatusSystemKey::Validated->value => $validatedOverride,
            WorkflowStatusSystemKey::ClosedWon->value => $closedWonOverride,
            WorkflowStatusSystemKey::ClosedLost->value => $closedLostOverride,
        ];

        foreach (WorkflowStatusSystemKey::tailKeys() as $key) {
            $this->forceCreateSystemRow($workflowId, $key, $sortOrder, $tailOverrides[$key->value]);
            $sortOrder += self::STEP;
        }
    }

    /**
     * Authoritative sync of $set's CUSTOM rows (id present = update, absent =
     * new; existing customs not included = deleted), resequencing sort_order
     * so 'open' stays first and the pinned tail rows 'validated'/'closed_won'/
     * 'closed_lost' stay last. A submitted row whose
     * `id` matches an existing SYSTEM row is routed to
     * assertMutableSystemRow() instead (everything but `group`, spec 0047
     * data contract) and never counted as a custom / never deleted.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statusRows
     */
    public function syncCustoms(?int $workflowId, array $statusRows): void
    {
        DB::transaction(function () use ($workflowId, $statusRows): void {
            $existing = OpportunityWorkflowStatus::query()
                ->where('opportunity_workflow_id', $workflowId)
                ->get()
                ->keyBy('id');

            [$systemRows, $customRows] = $this->partitionSubmitted($statusRows, $existing);

            $this->applySystemUpdates($systemRows, $existing);
            $orderedCustomIds = $this->applyCustomSync($workflowId, $customRows, $existing);
            $this->resequence($workflowId, $orderedCustomIds);
        });
    }

    /**
     * A system row accepts every descriptive change (name, description,
     * color, requires_note) but never a `group` one (spec 0047 data
     * contract): rejects, with a 422, any submission whose `group` differs
     * from what is currently persisted.
     *
     * @param  array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}  $submitted
     */
    public function assertMutableSystemRow(OpportunityWorkflowStatus $status, array $submitted): void
    {
        if ($submitted['group'] !== $status->group->value) {
            abort(422, "The '{$status->name}' status is a system status: its group cannot change.");
        }
    }

    /**
     * The purely DESCRIPTIVE attributes every row (system or custom) accepts:
     * name, description, color and the `requires_note` marker — everything
     * but `group`, which a system row may never change.
     *
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}  $row
     * @return array<string, mixed>
     */
    private function descriptiveAttributes(array $row): array
    {
        return [
            'name' => $row['name'],
            'description' => $row['description'],
            'color' => $row['color'],
            'requires_note' => $row['requires_note'],
        ];
    }

    /**
     * @param  array{name: string, description: ?string, color: ?string, requires_note: bool}|null  $override  client-supplied descriptive seed for this pinned row
     */
    private function forceCreateSystemRow(?int $workflowId, WorkflowStatusSystemKey $key, int $sortOrder, ?array $override = null): void
    {
        [$defaultName, $group] = match ($key) {
            WorkflowStatusSystemKey::Open => ['Aperta', WorkflowStatusGroup::Open],
            WorkflowStatusSystemKey::Validated => ['Validato', WorkflowStatusGroup::Validated],
            WorkflowStatusSystemKey::ClosedWon => ['Chiusa positiva', WorkflowStatusGroup::ClosedWon],
            WorkflowStatusSystemKey::ClosedLost => ['Chiusa negativa', WorkflowStatusGroup::ClosedLost],
        };

        OpportunityWorkflowStatus::query()->forceCreate([
            'opportunity_workflow_id' => $workflowId,
            'name' => $override['name'] ?? $defaultName,
            'description' => $override['description'] ?? null,
            'color' => $override['color'] ?? null,
            'sort_order' => $sortOrder,
            'system_key' => $key->value,
            'group' => $group,
            'requires_note' => $override['requires_note'] ?? false,
        ]);
    }

    /**
     * @param  array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}  $status
     */
    private function forceCreateCustomRow(?int $workflowId, array $status, int $sortOrder): void
    {
        OpportunityWorkflowStatus::query()->forceCreate([
            'opportunity_workflow_id' => $workflowId,
            'name' => $status['name'],
            'description' => $status['description'],
            'color' => $status['color'],
            'sort_order' => $sortOrder,
            'system_key' => null,
            'group' => $status['group'],
            'requires_note' => $status['requires_note'],
        ]);
    }

    /**
     * Splits $statusRows into [systemRows, customRows] by whether a
     * submitted `id` resolves to an existing SYSTEM row in $existing.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statusRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     * @return array{0: array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>, 1: array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>}
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
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $systemRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     */
    private function applySystemUpdates(array $systemRows, Collection $existing): void
    {
        foreach ($systemRows as $row) {
            /** @var OpportunityWorkflowStatus $status */
            $status = $existing->get($row['id']);

            $this->assertMutableSystemRow($status, $row);

            $status->fill($this->descriptiveAttributes($row))->save();
        }
    }

    /**
     * Updates/creates every submitted custom row (in submission order),
     * deletes the customs left out, and returns the kept ids IN ORDER — the
     * sequence resequence() places between 'open' and 'closed'.
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $customRows
     * @param  Collection<int, OpportunityWorkflowStatus>  $existing
     * @return array<int, int>
     */
    private function applyCustomSync(?int $workflowId, array $customRows, Collection $existing): array
    {
        $keptIds = [];

        foreach ($customRows as $row) {
            if ($row['id'] === null) {
                $created = OpportunityWorkflowStatus::query()->forceCreate([
                    'opportunity_workflow_id' => $workflowId,
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
            ->filter(static fn (OpportunityWorkflowStatus $status): bool => ! $status->isSystem() && ! in_array($status->id, $keptIds, true))
            ->each(static fn (OpportunityWorkflowStatus $status) => $status->delete());

        return $keptIds;
    }

    /**
     * @param  array<int, int>  $orderedCustomIds
     */
    private function resequence(?int $workflowId, array $orderedCustomIds): void
    {
        OpportunityWorkflowStatus::query()
            ->where('opportunity_workflow_id', $workflowId)
            ->where('system_key', WorkflowStatusSystemKey::Open->value)
            ->update(['sort_order' => 0]);

        $sortOrder = self::STEP;

        foreach ($orderedCustomIds as $id) {
            OpportunityWorkflowStatus::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }

        foreach (WorkflowStatusSystemKey::tailKeys() as $key) {
            OpportunityWorkflowStatus::query()
                ->where('opportunity_workflow_id', $workflowId)
                ->where('system_key', $key->value)
                ->update(['sort_order' => $sortOrder]);
            $sortOrder += self::STEP;
        }
    }
}
