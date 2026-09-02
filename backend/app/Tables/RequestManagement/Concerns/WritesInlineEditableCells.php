<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing write path for `request-management` (spec 0054, D-4):
 * split out of RequestManagementTableDefinition to stay within the file-size
 * budget (engineering.md §6). Spec 0086, D-2: the row is now a `quotes`
 * record — `updateWork()`'s subject changed from Opportunity to Quote, and
 * its return key from `opportunity` to `quote`, but the write path itself is
 * unchanged: every editable column still routes through ONE service call.
 *
 * The using class must expose `private readonly RequestManagementService
 * $service` (declared there, not here, to avoid a readonly-modifier
 * conflict with the trait — mirrors DelegatesUnaugmentedTableMethods'
 * documented convention for `$inner`).
 *
 * User directive 2026-08-31: the "Stato di lavorazione" cell joins that list
 * and brings back the `note` passthrough spec 0054 D-5 built for the
 * Opportunity's former `workflow_status` column (removed by spec 0083 D-2
 * together with the dimension it addressed) — retargeted at the Offerta's own
 * `quote_workflow_status_id`, the record this module operates on since spec
 * 0086.
 */
trait WritesInlineEditableCells
{
    /**
     * The `quote_workflow_status` column's field-permission key
     * (RequestColumnCatalog's `editableField`) — the only inline-editable
     * column of this domain carrying an optional `note` (spec 0054, D-5:
     * `notable: true`).
     */
    private const string WORKFLOW_STATUS_FIELD = 'quote_workflow_status_id';

    /**
     * The same column's DISPLAYED id — what `optionsFor()` is asked about
     * (the catalogue id), as opposed to the field key `updateCell()` receives
     * after TableCellUpdateService remapped it to `editableField`.
     */
    private const string WORKFLOW_STATUS_COLUMN = 'quote_workflow_status';

    /**
     * The `operator_ga2` column's field-permission key since spec 0097
     * (D-3a): the TEAM, because the lone `operator_id` key no longer exists
     * in RequestManagementAuthorization.
     */
    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    /**
     * ...and the key its WRITE still travels under (D-3b): the cell edits ONE
     * user, the OPERATOR slot, not the whole team.
     */
    private const string OPERATOR_FIELD = 'operator_id';

    /**
     * Every editable column of this domain writes through
     * RequestManagementService::updateWork() rather than a plain
     * `$row->update([...])`: `next_callback_at`/`source_id`/`product_lines`/
     * `general_notes`-adjacent fields live on the OPPORTUNITY (spec 0086,
     * D-2 — "i campi che vivono sull'Opportunità si leggono e si scrivono
     * attraverso `quote.opportunity`"), each carrying its own writer and
     * invariant (reminder-marker, cross-category coherence, etc.) behind
     * that same call. `$actor` is not part of this contract method's
     * signature (spec 0053): it is read from the auth guard, same precedent
     * as baseQuery()'s `Auth::user()` call.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Quote $row */
        /** @var User $actor */
        $actor = Auth::user();

        $data = [$this->writeKeyFor($columnId) => $value];

        // `note` is not part of this contract method's signature (spec 0053)
        // and is read from the ambient request the same way `$actor` is read
        // from the auth guard: every caller of the inline-edit engine runs
        // within one authenticated HTTP request, and TableCellUpdateService
        // has already refused a `note` on any column but this one
        // (`notable: true`). The mandatory-note rule itself is enforced
        // downstream by QuoteWorkflowStatusWriter, never here.
        if ($columnId === self::WORKFLOW_STATUS_FIELD) {
            $data['note'] = request()->input('note');
        }

        $result = $this->service->updateWork($row, $actor, $data);

        return $result['quote'];
    }

    /**
     * The `updateWork()` key a cell's field key writes under — the identity
     * for every column but the GA2 "Operatore" (spec 0097, D-3b).
     *
     * That one arrives as `manager_slots` because its PERMISSION is the
     * team's (the catalogue has no `operator_id` field any more, D-3), while
     * its WRITE is still the single OPERATOR slot: `updateWork()` expects an
     * ordered slot LIST under `manager_slots` and the cell carries one user
     * id, so the two keys are translated here rather than teaching either
     * side a second shape.
     */
    private function writeKeyFor(string $fieldKey): string
    {
        return $fieldKey === self::MANAGER_SLOTS_FIELD ? self::OPERATOR_FIELD : $fieldKey;
    }

    /**
     * The full `quote_workflow_status_id` catalogue (every workflow's
     * statuses, not just the set resolved for one specific offer — GET
     * /columns is domain-wide, not per-row), each carrying `requires_note` so
     * the grid's cell editor knows when to open the note dialog (spec 0054
     * D-5) and `color` so the same editor marks the option with the very dot
     * the badge cell and the work panel's picker already show. The
     * AUTHORITATIVE per-row membership check stays in
     * QuoteWorkflowStatusWriter regardless of what this list shows.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== self::WORKFLOW_STATUS_COLUMN) {
            return null;
        }

        return QuoteWorkflowStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'requires_note'])
            ->map(static fn (QuoteWorkflowStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'requires_note' => $status->requires_note,
            ])
            ->all();
    }
}
