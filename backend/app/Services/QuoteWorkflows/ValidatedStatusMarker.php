<?php

declare(strict_types=1);

namespace App\Services\QuoteWorkflows;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The OPTIONAL 'validated' system row of a QuoteWorkflowStatus set (user
 * directive 2026-08-03): unlike 'open'/'closed_won'/'closed_lost' — the
 * three mandatory rows WorkflowStatusWriter creates with every set — no set
 * is born with a validated row and none is defaulted onto a status. A row
 * carries it only while the client explicitly marks it (`system_key:
 * 'validated'` on the submitted row), at most one per set.
 *
 * Split out of WorkflowStatusWriter, whose other rules never move a row
 * BETWEEN the system and the custom bucket: here a submitted mark promotes a
 * custom row (or creates a brand-new one), and dropping the mark demotes the
 * current one back to custom. Runs BEFORE the writer partitions the payload,
 * so the partition sees each row's final identity.
 */
final class ValidatedStatusMarker
{
    /**
     * Reconciles $existing's validated row with the mark carried by
     * $statusRows, mutating the models in $existing IN PLACE so the caller's
     * partition step sees the post-transition identity. The marked row is
     * written HERE in full (descriptive fields included) — the caller skips
     * every row carrying the mark.
     *
     * A demotion is always EXPLICIT: it happens only when the current
     * validated row is itself submitted, carrying `system_key`, with a value
     * other than 'validated' — or when ANOTHER submitted row claims the mark,
     * since a set holds at most one. A payload that never mentions
     * `system_key` at all, as every pre-existing client and seeder does,
     * leaves the row untouched, exactly like the mandatory system rows (never
     * silently demoted nor deleted).
     *
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string, system_key_submitted?: bool}>  $statusRows
     * @param  Collection<int, QuoteWorkflowStatus>  $existing
     */
    public function apply(?int $workflowId, array $statusRows, Collection $existing): void
    {
        // Step 1: at most one row may claim the mark.
        $marked = $this->markedRow($statusRows);

        $current = $existing->first(
            static fn (QuoteWorkflowStatus $status): bool => $status->system_key === WorkflowStatusSystemKey::Validated->value,
        );

        // Step 2: the row that lost the mark goes back to being a custom one.
        if ($current !== null && ($marked === null || $marked['id'] !== $current->id)) {
            $this->release($current, $statusRows, claimedByAnother: $marked !== null);
        }

        if ($marked === null) {
            return;
        }

        // Step 3: the marked row is written here — created, promoted, or just
        // updated when it already was the validated one.
        if ($marked['id'] === null) {
            $this->create($workflowId, $marked);

            return;
        }

        $status = $existing->get($marked['id']);

        if ($status === null) {
            abort(422, "Unknown status id [{$marked['id']}] for this workflow.");
        }

        if ($status->system_key !== null && $status->system_key !== WorkflowStatusSystemKey::Validated->value) {
            abort(422, "The '{$status->name}' status is a system status: it cannot become the validated one.");
        }

        $status->forceFill([
            ...$this->descriptiveAttributes($marked),
            'system_key' => WorkflowStatusSystemKey::Validated->value,
            'group' => WorkflowStatusGroup::Validated,
        ])->save();
    }

    /**
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string}>  $statusRows
     * @return array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string}|null
     */
    private function markedRow(array $statusRows): ?array
    {
        $marked = array_values(array_filter(
            $statusRows,
            static fn (array $row): bool => ($row['system_key'] ?? null) === WorkflowStatusSystemKey::Validated->value,
        ));

        if (count($marked) > 1) {
            abort(422, 'Only one status can be marked as the validated one.');
        }

        return $marked[0] ?? null;
    }

    /**
     * @param  array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string, system_key_submitted?: bool}>  $statusRows
     */
    private function release(QuoteWorkflowStatus $current, array $statusRows, bool $claimedByAnother): void
    {
        $submitted = array_find($statusRows, static fn (array $row): bool => $row['id'] === $current->id);

        if ($submitted === null) {
            // Demoting a row the payload never mentions would leave it out of
            // the custom sync too — i.e. delete it. The client has to resubmit
            // it to move the mark elsewhere.
            if ($claimedByAnother) {
                abort(422, "The '{$current->name}' status is the validated one: resubmit it to move the mark to another status.");
            }

            return;
        }

        if (! $claimedByAnother && ($submitted['system_key_submitted'] ?? false) !== true) {
            return;
        }

        // The group comes from the payload: the row is a plain custom one from
        // now on, so the caller's custom sync would overwrite it anyway.
        $current->forceFill(['system_key' => null, 'group' => $submitted['group']])->save();
    }

    /**
     * @param  array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool, system_key: ?string}  $marked
     */
    private function create(?int $workflowId, array $marked): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::query()->forceCreate([
            'quote_workflow_id' => $workflowId,
            ...$this->descriptiveAttributes($marked),
            'sort_order' => 0,
            'system_key' => WorkflowStatusSystemKey::Validated->value,
            'group' => WorkflowStatusGroup::Validated,
        ]);
    }

    /**
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
}
