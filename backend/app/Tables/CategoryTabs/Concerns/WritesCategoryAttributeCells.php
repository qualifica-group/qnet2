<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs\Concerns;

use App\Models\User;
use App\Tables\RequestManagement\AttributeColumnBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The WRITE side of the `attr.<code>` columns (spec 0350, D-6), used with
 * `AppendsCategoryAttributeColumns` by the grids whose cells are editable
 * (quotes, work-orders). A write goes through the module's own CellWriter
 * (its update FormRequest -> Service::update -> AttributeValueWriter), so
 * validation, merge and activity log stay the form's single choke point and
 * the set validated against is the RECORD's, not the tab's.
 *
 * The using class resolves the two abstract hooks and, since
 * `DelegatesUnaugmentedTableMethods` declares the same two methods, picks
 * this trait's with `insteadof` (as `RequestManagementScopedTableDefinition`).
 */
trait WritesCategoryAttributeCells
{
    /**
     * Persists `[$code => $value]` through the module's CellWriter.
     */
    abstract protected function writeAttributeValue(Model $row, string $code, mixed $value, User $actor): Model;

    /**
     * The saved record's own applicable attributes (plain-array shape).
     *
     * @return Collection<int, array<string, mixed>>
     */
    abstract protected function applicableAttributesOf(Model $row): Collection;

    /**
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array
    {
        $ids = $this->inner->editableColumnIds($actor);
        // A table attribute is edited from the form only (spec 0180).
        $attributes = $this->categoryAttributes()->reject(static fn (array $row): bool => $row['type'] === 'table');

        if ($attributes->isEmpty() || ! $this->attributeValuesEditable($actor)) {
            return $ids;
        }

        return [...$ids, ...$this->attributeColumns->columnIds($attributes)];
    }

    /**
     * `TableCellUpdateService` already remapped the submitted `attr.<code>` to
     * the raw declaration's `editableField`, so the real code is recovered from
     * the ambient request's own `column` input, as
     * `WritesAttributeCells::updateCell()` does for Gestione Richieste.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        $code = $columnId === AttributeColumnBuilder::EDITABLE_FIELD
            ? $this->attributeColumns->codeFor((string) request()->input('column'))
            : null;

        if ($code === null) {
            $saved = $this->inner->updateCell($row, $columnId, $value);
        } else {
            /** @var User $actor */
            $actor = Auth::user();
            $saved = $this->writeAttributeValue($row, $code, $value, $actor);
        }

        // The PATCH response replaces the whole grid row, and the PATCH carries
        // no advanced-filter scope: the record's OWN attributes stand in for it,
        // so the written cell shows and the other `attr.*` cells are not blanked
        // (an edit of the Responsabili also sets the assignment date, task 428419).
        $this->rowAttributesOverride = $this->applicableAttributesOf($saved);

        return $saved;
    }
}
