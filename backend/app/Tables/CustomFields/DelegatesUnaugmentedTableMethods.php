<?php

declare(strict_types=1);

namespace App\Tables\CustomFields;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every `TableDefinition` method `CustomFieldAwareTableDefinition` does NOT
 * augment: pure one-line delegation to the wrapped `$inner` definition. Split
 * out to keep the decorator within the file-size budget (engineering.md §6)
 * — the augmented methods (columns/resolveConfig/baseQuery/mapRow/the
 * allow-list + derived hooks) are what spec 0021 T6 is actually about.
 *
 * The using class must expose a `TableDefinition $inner` property (declared
 * there, e.g. as a `private readonly` promoted constructor property — NOT
 * redeclared here to avoid a readonly-modifier conflict with the trait).
 */
trait DelegatesUnaugmentedTableMethods
{
    public function domain(): string
    {
        return $this->inner->domain();
    }

    public function resource(): string
    {
        return $this->inner->resource();
    }

    public function modelClass(): string
    {
        return $this->inner->modelClass();
    }

    public function authorizeViewAny(User $actor): bool
    {
        return $this->inner->authorizeViewAny($actor);
    }

    public function filters(): array
    {
        return $this->inner->filters();
    }

    public function actions(): array
    {
        return $this->inner->actions();
    }

    public function defaultSort(): array
    {
        return $this->inner->defaultSort();
    }

    public function defaultPagination(): array
    {
        return $this->inner->defaultPagination();
    }

    public function actionsFor(User $actor, Model $row): array
    {
        return $this->inner->actionsFor($actor, $row);
    }

    public function deleteModel(Model $model): void
    {
        $this->inner->deleteModel($model);
    }

    /**
     * Inline cell-editing (spec 0053): custom fields are not eligible for
     * inline edit in this round (no column declares `editable` for
     * `custom.*`), so this is pure passthrough — same as every other
     * unaugmented method.
     *
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array
    {
        return $this->inner->editableColumnIds($actor);
    }

    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return $this->inner->authorizeUpdate($actor, $row);
    }

    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        return $this->inner->updateCell($row, $columnId, $value);
    }

    public function authorizeDelete(User $actor, Model $row): bool
    {
        return $this->inner->authorizeDelete($actor, $row);
    }

    /**
     * Advanced filters (spec 0032) are a native-column/relation concern the
     * wrapped $inner definition owns entirely — a custom field is not (yet)
     * eligible for the advanced-filter panel, so this is pure passthrough.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return $this->inner->advancedFilters();
    }

    /**
     * @return array<int, string>
     */
    public function advancedFilterableIds(): array
    {
        return $this->inner->advancedFilterableIds();
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        return $this->inner->applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Aggregates (spec 0156, D-3): no domain computes one over a `custom.*`
     * column, so this is pure passthrough — $query already carries the
     * custom-field join `baseQuery()` adds, which changes nothing for a
     * `sum()`/`count()` against the host table's own columns.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int|float|string|null>
     */
    public function aggregates(Builder $query): array
    {
        return $this->inner->aggregates($query);
    }

    /**
     * Tree support (spec 0157, D-1) is a `$inner` domain concern, not a
     * custom-field one, so this is pure passthrough.
     */
    public function supportsTree(): bool
    {
        return $this->inner->supportsTree();
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applyTreeScope(Builder $query, ?int $parentId): void
    {
        $this->inner->applyTreeScope($query, $parentId);
    }

    /**
     * The SSRM block cap (spec 0157) is a `$inner` domain concern, so this
     * is pure passthrough.
     */
    public function maxRowsLimit(): int
    {
        return $this->inner->maxRowsLimit();
    }
}
