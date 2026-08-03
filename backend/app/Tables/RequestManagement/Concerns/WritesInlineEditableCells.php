<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing write path for `request-management` (spec 0054, D-4/
 * D-5): split out of RequestManagementTableDefinition to stay within the
 * file-size budget (engineering.md §6) — everything below is the WHOLE of
 * this domain's spec 0054 override, a single cohesive concern.
 *
 * The using class must expose `private readonly RequestManagementService
 * $service` (declared there, not here, to avoid a readonly-modifier
 * conflict with the trait — mirrors DelegatesUnaugmentedTableMethods'
 * documented convention for `$inner`).
 */
trait WritesInlineEditableCells
{
    /**
     * The `workflow_status` column's field-permission key
     * (RequestColumnCatalog's `editableField`) — the only inline-editable
     * column carrying an optional `note` (spec 0054, D-5).
     */
    private const string WORKFLOW_STATUS_FIELD = 'opportunity_workflow_status_id';

    /**
     * Every editable column of this domain (`next_callback_at`, D-4;
     * `opportunity_workflow_status_id`, D-5) write through
     * RequestManagementService::updateWork() rather than a plain
     * `$row->update([...])`: `next_callback_at` is OUTSIDE
     * Opportunity::$fillable (mass-assignment guard, spec 0052 D-2) and
     * carries its own reminder-marker invariant; the workflow status carries
     * the resolved-set membership check (AC-011) and the mandatory-note rule
     * for a `requires_note` target (D-5) — both live in updateWork(), the
     * ONE choke point both write channels (this override and the work
     * panel's `UpdateRequestRequest`) pass through, so they can never
     * diverge — including `products_of_interest` (user directive
     * 2026-07-23), whose cross-category rule lives in
     * OpportunityProductInterestWriter behind that same call. Neither `$actor` nor `note` are part of this contract
     * method's signature (spec 0053): `$actor` is read from the auth guard,
     * same precedent as baseQuery()'s `Auth::user()` call; `note` is read
     * from the ambient request the same way, since every caller of the
     * inline-edit engine runs within one authenticated HTTP request.
     *
     * Spec 0075: `product_lines` (the "Categoria prodotto" cell) joins that
     * list without a line of code here — the field key IS the payload key
     * updateWork() already understands, and every rule of the set lives in
     * RequestProductLineWriter, behind that same call.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Opportunity $row */
        /** @var User $actor */
        $actor = Auth::user();

        $data = [$columnId => $value];

        if ($columnId === self::WORKFLOW_STATUS_FIELD) {
            $data['note'] = request()->input('note');
        }

        $result = $this->service->updateWork($row, $actor, $data);

        return $result['opportunity'];
    }

    /**
     * The full `opportunity_workflow_status_id` catalogue (every workflow's
     * statuses, not just the one resolved for a specific opportunity — GET
     * /columns is domain-wide, not per-row), each carrying `requires_note` so
     * the grid's cell editor knows when to open the note dialog (spec 0054,
     * contract) and `color` so the same editor marks the option with the very
     * dot the badge cell and the work panel's picker already show for that
     * status. The AUTHORITATIVE per-row membership check (AC-011) stays
     * in RequestManagementService::updateWork() regardless of what this list
     * shows.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'workflow_status') {
            return null;
        }

        return OpportunityWorkflowStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'requires_note'])
            ->map(static fn (OpportunityWorkflowStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'requires_note' => $status->requires_note,
            ])
            ->all();
    }
}
