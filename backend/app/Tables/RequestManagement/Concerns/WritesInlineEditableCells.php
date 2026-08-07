<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Quote;
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
 */
trait WritesInlineEditableCells
{
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

        $result = $this->service->updateWork($row, $actor, [$columnId => $value]);

        return $result['quote'];
    }
}
