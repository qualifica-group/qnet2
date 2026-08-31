<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement\Concerns;

use App\Models\Quote;
use App\Models\User;
use App\Tables\RequestManagement\AttributeColumnBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Inline cell-editing for `attr.<code>` columns (spec 0064 §M4; restored by
 * the user directive 2026-08-31 on the OFFER's own `quotes.attribute_values`,
 * the storage spec 0084 D-1 moved "Informazioni aggiuntive" to): split out of
 * `RequestManagementScopedTableDefinition` to stay within the file-size
 * budget (engineering.md §6) — mirrors the sibling
 * `App\Tables\RequestManagement\Concerns\WritesInlineEditableCells` (the
 * INNER definition's own spec 0054 write path).
 *
 * The using class must expose `private readonly TableDefinition $inner`,
 * `private readonly AttributeGridColumns $attributeColumns`, a private
 * `categoryAttributes(): Collection` method, plus a settable
 * `?Collection $rowAttributesOverride` property (all declared there, not
 * here, to avoid a readonly-modifier conflict — mirrors
 * `DelegatesUnaugmentedTableMethods`'s documented convention for `$inner`).
 */
trait WritesAttributeCells
{
    /**
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array
    {
        $ids = $this->inner->editableColumnIds($actor);
        $attributes = $this->categoryAttributes();

        if ($attributes->isEmpty() || ! $this->attributeColumns->valuesEditable($actor)) {
            return $ids;
        }

        return [...$ids, ...$this->attributeColumns->columnIds($attributes)];
    }

    /**
     * `attr.<code>` writes go through `RequestManagementService::updateWork()`
     * (spec 0064 §M4) — never a direct `attribute_values` write — so
     * validation/normalization/merge/activity-log stay the SAME single choke
     * point the PATCH endpoint and the work panel share (that call reaches
     * `QuoteAttributeValueWriter`, which validates against the set
     * `RequestAttributeResolver` resolves for the ROW ITSELF, not against the
     * category tab the client happened to be on).
     *
     * `TableCellUpdateService` (D-1, spec 0054) ALREADY remapped `$columnId`
     * from the client's submitted `attr.<code>` to the raw declaration's
     * `editableField` (`AttributeColumnBuilder::EDITABLE_FIELD`) before ever
     * calling this method — the SAME convention every relation column uses
     * (e.g. `operator_ga2` -> `operator_id`) — so the real code is recovered
     * from the ambient request's own `column` input, mirroring how the
     * sibling `WritesInlineEditableCells::updateCell()` reads `note` the same
     * way (both live within the one authenticated HTTP request the PATCH
     * endpoint handles).
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        if ($columnId !== AttributeColumnBuilder::EDITABLE_FIELD) {
            return $this->inner->updateCell($row, $columnId, $value);
        }

        $code = $this->attributeColumns->codeFor((string) request()->input('column'));

        if ($code === null) {
            return $this->inner->updateCell($row, $columnId, $value);
        }

        /** @var Quote $row */
        /** @var User $actor */
        $actor = Auth::user();

        $result = $this->service->updateWork($row, $actor, ['attribute_values' => [$code => $value]]);
        $quote = $result['quote'];

        // The PATCH endpoint carries no category-scope param of its own
        // (unlike columns/rows), so the row's OWN applicable attributes stand
        // in for "which attr.* columns this response shows" — never the
        // "Tutte" tab's empty default, which would strip the very cell just
        // written from the re-mapped row.
        $this->rowAttributesOverride = $this->attributeColumns->rowAttributes($quote);

        return $quote;
    }
}
