<?php

namespace Tests\Stubs;

use App\Models\BusinessFunction;
use App\Models\User;
use App\Tables\AbstractTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal, test-only TableDefinition backing the custom-filter-rules engine
 * suite (spec 0158): a controlled column catalogue covering every
 * CustomFilterRuleApplier/CustomFilterRuleValidator branch — text/number/
 * boolean/date (a real DATETIME column, `created_at`, to exercise the
 * day-portable comparisons), a derived `set` relation (`manager`, via
 * `whereHas`) and the two edge-case columns (`score`: filterable, no
 * discrete value list; `weird`: filterable but no usable rule type) — on top
 * of the REAL `business_functions` table, reusing its Policy/permissions
 * under a fake `stub-custom-filter-rules` domain key, exactly like
 * StubExportTableDefinition/StubAdvancedFilterTableDefinition.
 */
class StubCustomFilterRuleTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'stub-custom-filter-rules';
    }

    /**
     * @return class-string<BusinessFunction>
     */
    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    /**
     * @return Builder<BusinessFunction>
     */
    public function baseQuery(): Builder
    {
        return BusinessFunction::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'text', 'searchable' => true],
            ['id' => 'is_business_unit', 'label' => 'Business unit', 'type' => 'boolean', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'boolean'],
            // A real DATETIME column (see the migration): exercises the
            // day-portable comparison CustomFilterRuleApplier builds directly
            // (FilterApplier's raw '>'/'<' is NOT day-portable on datetime).
            ['id' => 'created_at', 'label' => 'Created', 'type' => 'datetime', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'date'],
            // Derived `set` column: whereHas('manager', ...) on the related
            // user's own `name` — see applyDerivedFilter() below.
            ['id' => 'manager', 'label' => 'Manager', 'type' => 'text', 'visible' => true, 'sortable' => false, 'filterable' => true, 'filterType' => 'set'],
            // No discrete value list: blank/not_blank must 422 (spec 0158
            // contract), while every other operator stays usable.
            ['id' => 'score', 'label' => 'Score', 'type' => 'number', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'number', 'hasFilterValues' => false],
            // Filterable (grid-wise) but no filterType at all: out of scope
            // for a custom filter rule (spec 0158 contract: "qualsiasi altro
            // filterType (null) -> colonna non usabile nelle regole").
            ['id' => 'weird', 'label' => 'Weird', 'type' => 'text', 'visible' => false, 'sortable' => false, 'filterable' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'is_business_unit', 'type' => 'boolean'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'manager', 'type' => 'set'],
            ['columnId' => 'score', 'type' => 'number'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'id', 'direction' => 'asc']];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var BusinessFunction $row */
        return ['id' => $row->id, 'name' => $row->name];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    /**
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId !== 'manager') {
            return false;
        }

        $values = $filter['values'] ?? null;
        $names = is_array($values) ? array_values(array_filter($values, static fn ($value): bool => is_string($value) && $value !== '')) : [];
        $matchesBlank = is_array($values) && in_array(null, $values, true);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('manager', fn (Builder $related) => $related->whereIn('name', $names));
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave('manager');
            }
        });

        return true;
    }
}
