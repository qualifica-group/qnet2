<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing write path for `request-management` (spec 0054, D-4):
 * split out of RequestManagementTableDefinition to stay within the file-size
 * budget (engineering.md §6). Spec 0083, D-2: the `workflow_status` column
 * and its note-carrying write are GONE — the Opportunity resolves no working
 * state of its own any more, so this trait is now the D-4 override alone.
 *
 * The using class must expose `private readonly RequestManagementService
 * $service` (declared there, not here, to avoid a readonly-modifier
 * conflict with the trait — mirrors DelegatesUnaugmentedTableMethods'
 * documented convention for `$inner`).
 */
trait WritesInlineEditableCells
{
    /**
     * Every editable column of this domain writes through
     * RequestManagementService::updateWork() rather than a plain
     * `$row->update([...])`: `next_callback_at` is OUTSIDE
     * Opportunity::$fillable (mass-assignment guard, spec 0052 D-2) and
     * carries its own reminder-marker invariant — including
     * `products_of_interest` (user directive 2026-07-23), whose
     * cross-category rule lives in OpportunityProductInterestWriter behind
     * that same call. `$actor` is not part of this contract method's
     * signature (spec 0053): it is read from the auth guard, same precedent
     * as baseQuery()'s `Auth::user()` call.
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

        $result = $this->service->updateWork($row, $actor, [$columnId => $value]);

        return $result['opportunity'];
    }
}
