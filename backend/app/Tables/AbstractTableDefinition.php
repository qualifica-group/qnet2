<?php

namespace App\Tables;

use App\Enums\AdvancedFilterType;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Services\Table\AdvancedFilterApplier;
use App\Tables\Concerns\HandlesBlankSetFilter;
use App\Tables\Concerns\InjectsDefaultIdColumn;
use App\Tables\Concerns\ResolvesColumnConfig;
use App\Tables\Concerns\ResolvesEditableColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Shared resolution for every concrete TableDefinition.
 *
 * Lifted VERBATIM from the old UserTableService::config() + the whitelist
 * helpers (sortableColumns/filterableColumns), parameterized on the concrete
 * definition's columns()/filters()/actions() instead of config('tables.users').
 *
 * A concrete definition only declares its model (baseQuery), its catalogues
 * (columns/filters/actions), defaults, and the two row-level hooks
 * (mapRow/actionsFor) + dynamic option resolution (optionsFor()). The
 * cross-cutting parts (permission filtering of actions, dynamic option
 * resolution on columns/filters, whitelist derivation) live here once.
 */
abstract class AbstractTableDefinition implements TableDefinition
{
    use HandlesBlankSetFilter;
    use InjectsDefaultIdColumn;
    use ResolvesColumnConfig;
    use ResolvesEditableColumns;

    public function resource(): string
    {
        return $this->domain();
    }

    /**
     * Fail-safe default authorization: delegate to the model's Policy `viewAny`.
     *
     * A definition must declare its model (modelClass()) and therefore inherits
     * the standard `viewAny` gate WITHOUT being able to "forget" the check or
     * return a hardcoded `true`. This is fail-closed: if no Policy/permission is
     * registered for the model, Gate::allows() returns false (table denied),
     * never fail-open. Override only to add domain-specific rules on top.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('viewAny', $this->modelClass());
    }

    /**
     * Resolve the table schema for the given actor.
     *
     * Columns and filters are static (visibility is a frontend concern), but
     * their options may be dynamic (resolved via optionsFor) and the action
     * catalogue is filtered to the keys the actor is allowed to use, so the UI
     * never advertises an action the actor can never perform.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array
    {
        $layout = $this->defaultColumnLayout();
        $editableIds = $this->editableColumnIds($actor);

        return [
            'resource' => $this->resource(),
            'columns' => array_map(
                fn (array $column): array => $this->resolveColumn($column, $actor, $layout, $editableIds),
                $this->columnsWithDefaultId(),
            ),
            'filters' => $this->resolveFilters($actor),
            'actions' => $this->resolveActions($actor),
            'defaultSort' => $this->defaultSort(),
            'defaultPagination' => $this->defaultPagination(),
            // Real columns the global quick-search spans (spec 0009). The frontend
            // shows the search box only when non-empty and builds its placeholder
            // from these columns' labels.
            'searchable' => $this->searchableColumnIds(),
            // Advanced-filter catalogue (spec 0032), ordered, internal fields
            // stripped. `appliedAdvancedFilters` defaults to null here; the
            // per-user persisted state (TableFilterStateService) overwrites it,
            // exactly like `filterState`/`filtersCustomized`.
            'advancedFilters' => $this->resolveAdvancedFilters(),
            'appliedAdvancedFilters' => null,
        ];
    }

    /**
     * Default presentation layout per column id (ADR-0004).
     *
     * `order` defaults to the 1-based declaration position when a column does not
     * declare an explicit `order`; `width` defaults to null (the frontend applies
     * its own default width). This is the single baseline used BOTH to render the
     * default config and to compute the sparse user delta on save, so the two can
     * never drift.
     *
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array
    {
        $layout = [];
        $position = 0;

        foreach ($this->columnsWithDefaultId() as $column) {
            $position++;

            $layout[$column['id']] = [
                'visible' => (bool) ($column['visible'] ?? true),
                'width' => isset($column['width']) ? (int) $column['width'] : null,
                'order' => (int) ($column['order'] ?? $position),
            ];
        }

        return $layout;
    }

    /**
     * Resolve the advanced-filter catalogue (spec 0032) into its FE-facing
     * shape: ordered by `order`, stripped of the INTERNAL `target`/`operator`
     * fields the definition uses to apply the filter server-side
     * (AdvancedFilterApplier) — mirrors resolveFilters()'s stripping of
     * `optionsSource`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveAdvancedFilters(): array
    {
        $descriptors = $this->advancedFilters();

        usort(
            $descriptors,
            static fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0),
        );

        return array_map(
            static function (array $descriptor): array {
                unset($descriptor['target'], $descriptor['operator']);

                return $descriptor;
            },
            $descriptors,
        );
    }

    /**
     * Resolve the filter catalogue: replace any declarative `optionsSource`
     * marker with the real dynamic options so no dead declarative field is left
     * for the frontend to resolve.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveFilters(User $actor): array
    {
        return array_map(
            function (array $filter) use ($actor): array {
                $columnId = $filter['columnId'] ?? null;

                if (is_string($columnId)) {
                    $dynamic = $this->optionsFor($columnId, $actor);

                    if ($dynamic !== null) {
                        unset($filter['optionsSource']);
                        $filter['options'] = $dynamic;
                    }
                }

                return $filter;
            },
            $this->filters(),
        );
    }

    /**
     * Filter the action catalogue down to keys the actor may use.
     * An action without a `permission` is available to any authenticated user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveActions(User $actor): array
    {
        $resolved = [];

        foreach ($this->actions() as $action) {
            $permission = $action['permission'] ?? null;

            if ($permission !== null && ! $actor->can($permission)) {
                continue;
            }

            unset($action['permission']);
            $resolved[] = $action;
        }

        return array_values($resolved);
    }

    /**
     * Sortable real DB column names (whitelist for ORDER BY).
     *
     * @return array<int, string>
     */
    public function sortableColumnIds(): array
    {
        $ids = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['sortable'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Filterable real DB column names (or derived keys) — whitelist for WHERE.
     *
     * @return array<int, string>
     */
    public function filterableColumnIds(): array
    {
        return array_keys($this->filterableColumnMap());
    }

    /**
     * Real DB columns flagged `searchable` — the allow-list for the global
     * quick-search OR-LIKE (spec 0009). Only columns that map to a real base
     * column may be flagged (a bound `LIKE` runs on each); derived keys are
     * never eligible.
     *
     * @return array<int, string>
     */
    public function searchableColumnIds(): array
    {
        $ids = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['searchable'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Filterable columns keyed by id, each with its raw definition (used by the
     * service to resolve filterType/options safely). Derived columns (e.g.
     * `roles`) are included and handled specially by the definition.
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array
    {
        $columns = [];

        foreach ($this->columnsWithDefaultId() as $column) {
            if (($column['filterable'] ?? false) === true) {
                $columns[$column['id']] = $column;
            }
        }

        return $columns;
    }

    /**
     * Default: no advanced filters (spec 0032). A domain opts in by
     * overriding with its own catalogue.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [];
    }

    /**
     * Advanced-filter ids whitelisted for the `advancedFilters` request key —
     * the `name` of every descriptor declared in advancedFilters().
     *
     * @return array<int, string>
     */
    public function advancedFilterableIds(): array
    {
        return array_column($this->advancedFilters(), 'name');
    }

    /**
     * Default: delegate to AdvancedFilterApplier. `target` (falling back to
     * `$name` when the descriptor omits it) is resolved server-side ONLY from
     * this definition's own catalogue — never client input — so it is trusted
     * as either a real DB column or, for `type: relation`, a plain Eloquent
     * relation method name. Always returns true for a well-formed descriptor;
     * false only when the descriptor carries no valid `type` (defence in
     * depth against a malformed catalogue entry).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        $type = $descriptor['type'] ?? null;

        if (! $type instanceof AdvancedFilterType) {
            return false;
        }

        $target = is_string($descriptor['target'] ?? null) ? $descriptor['target'] : $name;

        app(AdvancedFilterApplier::class)->apply($query, $type, $target, $value, $descriptor);

        return true;
    }

    /**
     * Default: no derived columns. Concrete definitions override to handle
     * derived columns (e.g. `roles`) that have no real DB column.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return false;
    }

    /**
     * Default: no derived columns. Concrete definitions override to ORDER BY a
     * derived value (e.g. a related column resolved via a correlated subquery).
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        return false;
    }

    /**
     * Default: no derived column owns its distinct-values resolution: let the
     * generic engine `SELECT DISTINCT` the real column. Concrete definitions
     * override for DERIVED columns (e.g. `roles`, `permissions`, geo names).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return null;
    }

    /**
     * Default: no derived searchable column — let the generic engine's plain
     * `orWhere($columnId, 'like', $pattern)` run against the real column.
     * Concrete definitions override for a DERIVED searchable column (e.g.
     * `city`/`street` on `operational-sites`, spec 0011).
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return false;
    }

    /**
     * Default: a plain delete, identical to calling the model directly.
     * Concrete definitions override when the domain's single-delete endpoint
     * delegates to a Service that enforces a guard beyond the Policy check
     * (e.g. the last-super-admin guard, a protected-system-row guard).
     */
    public function deleteModel(Model $model): void
    {
        $model->delete();
    }

    /**
     * Default: the model's own Policy governs the destructive action, same
     * fail-safe pattern as authorizeViewAny()/authorizeUpdate() (no Policy
     * registered → false, never fail-open).
     */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return Gate::forUser($actor)->allows('delete', $row);
    }

    // editableColumnIds()/authorizeUpdate()/updateCell() defaults (spec 0053)
    // live in ResolvesEditableColumns; optionsFor()/badgesFor()/enumKeyFor()/
    // resolveColumn() live in ResolvesColumnConfig (file-size budget,
    // engineering.md §6).

    /**
     * Default: no aggregates (spec 0156, D-3). A domain opts in by
     * overriding.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int|float|string|null>
     */
    public function aggregates(Builder $query): array
    {
        return [];
    }

    /**
     * Default: no tree/hierarchical support (spec 0157, D-1). A domain opts
     * in by overriding.
     */
    public function supportsTree(): bool
    {
        return false;
    }

    /**
     * Default: a no-op. Only ever reached for a domain that overrides
     * supportsTree() to true (spec 0157, D-1).
     *
     * @param  Builder<Model>  $query
     */
    public function applyTreeScope(Builder $query, ?int $parentId): void
    {
        // Intentionally empty: no domain narrows its query by default.
    }

    /**
     * Default: the shared SSRM cap every domain used before spec 0157. A
     * domain opts into a wider block by overriding (e.g. tasks' Kanban).
     */
    public function maxRowsLimit(): int
    {
        return BaseApiController::MAX_LIMIT;
    }
}
