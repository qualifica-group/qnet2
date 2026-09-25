<?php

namespace App\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a single domain's table (config + rows).
 *
 * A definition declares EVERYTHING about its table: the base query, the
 * column/filter/action catalogues, the default sort/pagination, how a model
 * maps to a row, and which row-actions are allowed for a given actor. The
 * generic TableService + TableController operate ONLY through this contract,
 * so the security-critical SSRM engine lives in exactly one place and every
 * domain inherits it identically.
 *
 * @phpstan-type ColumnDefinition array{id: string, label: string, type: string, visible: bool, sortable: bool, filterable: bool, filterType?: string|null, hasFilterValues?: bool, options?: array<int, scalar>|null, badges?: array<int, array<string, mixed>>|null, permission?: string|null, editable?: bool, nullable?: bool, rules?: array<int, mixed>, editableField?: string, relation?: array{resource: string}}
 * @phpstan-type FilterDefinition array{columnId: string, type: string, options?: array<int, scalar>|null, optionsResolver?: callable}
 * @phpstan-type ActionDefinition array{key: string, label: string, icon: string, type: string, confirm: bool, permission?: string|null}
 */
interface TableDefinition
{
    /**
     * Permission prefix / domain key, e.g. "users". Also the `resource` field
     * returned in the config so the frontend can pick its renderer registry.
     */
    public function domain(): string;

    /**
     * The `resource` key exposed to the frontend (defaults to domain()).
     */
    public function resource(): string;

    /**
     * The Eloquent model class this table is built on. Drives the fail-safe
     * default `viewAny` authorization (via the model's Policy) so a definition
     * can never accidentally expose its table without a permission check.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Authorize table access (viewAny). False → 403 on both endpoints.
     *
     * Defaults (in AbstractTableDefinition) to the model's Policy `viewAny`
     * (fail-safe): a definition that forgets to override still requires the
     * permission. Override only to add domain-specific rules.
     */
    public function authorizeViewAny(User $actor): bool;

    /**
     * Base Eloquent query (with eager loads, no N+1).
     *
     * @return Builder<Model>
     */
    public function baseQuery(): Builder;

    /**
     * Column catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array;

    /**
     * Filter catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array;

    /**
     * Action catalogue (raw declarative definitions).
     *
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array;

    /**
     * Default sort applied when the request carries no (whitelisted) sortModel.
     *
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array;

    /**
     * Default pagination hint for the initial grid state.
     *
     * @return array{limit: int}
     */
    public function defaultPagination(): array;

    /**
     * Map one model → row array (real fields only, hidden never exposed).
     * Must NOT include `actions` (the service attaches it via actionsFor()).
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array;

    /**
     * Allowed action keys for THIS row, via the domain Policy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array;

    /**
     * Real DB column names whitelisted for ORDER BY.
     *
     * @return array<int, string>
     */
    public function sortableColumnIds(): array;

    /**
     * Real DB column names (or derived keys) whitelisted for WHERE.
     *
     * @return array<int, string>
     */
    public function filterableColumnIds(): array;

    /**
     * Column ids whitelisted for the global quick-search OR-LIKE (spec 0009).
     * Normally real columns of the base table (the generic engine runs a
     * bound `LIKE` on each); a DERIVED id (no real column, e.g. a geo name
     * resolved from a related address — spec 0011) is also allowed as long as
     * the definition implements `applyDerivedSearch()` for it, so the generic
     * fallback is never reached. Empty ⇒ the domain has no global search (no
     * search box).
     *
     * @return array<int, string>
     */
    public function searchableColumnIds(): array;

    /**
     * Resolved config for the given actor: columns/filters/actions filtered by
     * permission, dynamic options resolved. This is the GET /columns payload
     * (the DEFAULT layout, before any per-user preference is merged in).
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(User $actor): array;

    /**
     * Default presentation layout per column id: the authoritative baseline
     * against which a user's saved preferences are merged (read) and diffed
     * (write) — see ADR-0004. Keys are column ids; values carry only the
     * user-overridable presentation properties.
     *
     * @return array<string, array{visible: bool, width: int|null, order: int}>
     */
    public function defaultColumnLayout(): array;

    /**
     * Filterable columns keyed by DB column name, each with its raw definition
     * (used by the service to resolve filterType/options safely).
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterableColumnMap(): array;

    /**
     * Hook for DERIVED columns (no real DB column, e.g. `roles` → whereHas).
     * Return true when the definition handled the filter itself; false to let
     * the generic engine apply it against the real column `$columnId`.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool;

    /**
     * Hook for DERIVED columns that have no real DB column to ORDER BY (e.g.
     * `user_type` resolved from a relation). Return true when the definition
     * applied the sort itself; false to let the generic engine ORDER BY the real
     * column `$columnId`. `$direction` is already normalized to `asc`/`desc`.
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool;

    /**
     * Hook for the Excel-like distinct-values endpoint (spec 0004). Return the
     * resolved list of distinct scalar values (as strings, already
     * search-filtered and capped to `$limit`) when the definition owns a
     * DERIVED column with no real DB column to `SELECT DISTINCT` on (e.g.
     * `roles`, `permissions`, geo names). Return null to let the generic
     * engine run a plain `SELECT DISTINCT` on the real column `$columnId`.
     *
     * `$query` is the base query with every OTHER active filter already
     * applied (never the target column's own filter — the column must not
     * auto-restrict its own list).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array;

    /**
     * Hook for the global quick-search (spec 0009) on a DERIVED searchable
     * column with no real DB column of its own (e.g. `city`/`street` on
     * `operational-sites`, resolved from the primary address — spec 0011).
     * `$query` is the OR-group builder TableService::applySearch() already
     * opened, so an implementation adds its own `orWhere`/`orWhereHas` to stay
     * part of the OR combination. `$pattern` is the already-escaped,
     * `%…%`-wrapped bound LIKE value. Return true when handled; false to let
     * the generic engine fall back to a plain `orWhere($columnId, 'like',
     * $pattern)` against the real column `$columnId`.
     *
     * Default (AbstractTableDefinition): always false — every domain that
     * does not override it keeps its unchanged flat OR-LIKE behavior.
     *
     * @param  Builder<Model>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool;

    /**
     * Advanced-filter catalogue (raw declarative descriptors, spec 0032): the
     * second-level, backend-driven filter panel above the grid. Each entry
     * additionally carries the INTERNAL `target` (real DB column, or relation
     * method name for `type: relation`) and an optional `operator` — neither is
     * ever emitted to the frontend (stripped in resolveConfig(), see
     * AdvancedFilterApplier). Default (in AbstractTableDefinition): `[]` — a
     * domain opts in by overriding.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array;

    /**
     * Advanced-filter ids whitelisted for the `advancedFilters` key of
     * `POST /tables/{domain}/rows` (and its persistence twins) — the `name` of
     * every descriptor declared in advancedFilters().
     *
     * @return array<int, string>
     */
    public function advancedFilterableIds(): array;

    /**
     * Apply one advanced filter's already-validated value to the query.
     *
     * Default (AbstractTableDefinition) delegates to AdvancedFilterApplier for
     * a `target` that is either a real DB column (any type) or — for
     * `type: relation` — a plain Eloquent relation method name, matched
     * generically via `whereHas()` on the related model's own primary key. A
     * concrete definition overrides this method ONLY for genuinely derived
     * logic a relation name + id-match cannot express (e.g. searching a related
     * model by a non-id field): it checks its own filter name(s) first, then
     * falls back to `parent::applyAdvancedFilter()` for everything else. Return
     * true when handled.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool;

    /**
     * Delete a single row already authorized (the 'delete' ability) for the
     * generic bulk-delete endpoint (POST /api/tables/{domain}/bulk-delete).
     *
     * Default (AbstractTableDefinition): a plain `$model->delete()`. Override
     * when the domain's single-delete endpoint delegates to a Service that
     * enforces MORE than the Policy check (e.g. UserService::delete's
     * last-super-admin guard, RoleService::delete's protected-role guard) —
     * delegating here keeps the bulk path and the single-delete path under
     * the exact same guard, never a shortcut around it. An implementation MAY
     * throw (AuthorizationException or an HTTP exception, e.g. via `abort()`)
     * to signal a domain guard rejected this specific row; the bulk-delete
     * service catches it and reports the row as `guarded` without aborting
     * the rest of the batch.
     */
    public function deleteModel(Model $model): void;

    /**
     * Per-row authorization for the bulk-delete path, the exact counterpart
     * of authorizeUpdate() for the destructive action. Default
     * (AbstractTableDefinition): `Gate::forUser($actor)->allows('delete', $row)`,
     * the fail-safe pattern every domain inherits. Override when the domain's
     * delete ability is NOT governed by modelClass()'s own Policy (e.g.
     * RequestManagementTableDefinition, whose model is Opportunity but whose
     * permission prefix is its own `request-management.*`) — without the
     * override such a domain would be gated by a FOREIGN permission.
     */
    public function authorizeDelete(User $actor, Model $row): bool;

    /**
     * Column ids where inline cell-editing (spec 0053) is allowed for
     * $actor: declared `'editable' => true` in the catalogue AND
     * `{resource}.update` AND the per-field DB permission
     * (`role_field_permissions`, via AuthorizationRegistry) all allow it.
     * Fail-safe (D-3): a resource unregistered in config/authorization.php,
     * or a column id with no matching field key in that resource's
     * catalogue, is never editable — regardless of the declaration. For a
     * RELATION column (spec 0054, D-1: `'editableField' => 'operator_id'`),
     * that field-key check resolves against `editableField` instead of the
     * (display) column id — both branches stay fail-safe.
     *
     * Drives the per-column `editable` flag resolveConfig() emits in GET
     * /columns (D-2) — a UI HINT only. The PATCH endpoint never trusts this
     * list: it re-derives its own guards against the REAL row (D-2: "il
     * config è un suggerimento, la catena di guardie del PATCH è la
     * verità").
     *
     * @return array<int, string>
     */
    public function editableColumnIds(User $actor): array;

    /**
     * Per-row authorization for inline cell-editing (spec 0053, D-4),
     * orthogonal to the per-column allow-list above: a cell is editable iff
     * column editable AND row editable. Default (AbstractTableDefinition):
     * `Gate::forUser($actor)->allows('update', $row)`, the same fail-safe
     * pattern as authorizeViewAny(). Override when the domain's update
     * ability is NOT governed by modelClass()'s own Policy (e.g.
     * RequestManagementTableDefinition, whose model is Opportunity but whose
     * permission prefix is its own `request-management.*`).
     */
    public function authorizeUpdate(User $actor, Model $row): bool;

    /**
     * Persist one cell's new (already-validated) value on an
     * already-authorized $row (spec 0053, D-7) and return the fresh model.
     * Default (AbstractTableDefinition): a plain
     * `$row->update([$columnId => $value])` — requires $columnId to be in
     * $row's `$fillable`, so a misdeclared editable column fails LOUDLY (a
     * mass-assignment exception) instead of silently no-op-ing. Override
     * when the write must go through a domain Service instead of a raw
     * Eloquent update (business rules, a column outside `$fillable`) — an
     * override that bypasses this Eloquent update() cycle MUST emit the
     * activity-log entry itself (D-8), since LogsModelActivity only
     * observes the standard save/update lifecycle.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model;

    /**
     * Aggregate values computed over the WHOLE filtered result set (spec
     * 0156, D-3), not just the current page — e.g. a footer total. `$query`
     * already carries every column filter, advanced filter and the quick
     * search applied (TableService::rows()), but is NOT yet sorted or
     * paginated, so a `sum()`/`count()` against it answers "the filtered
     * set", never "this page". Default (AbstractTableDefinition): `[]` — no
     * domain computes an aggregate unless it opts in, and
     * TableController::rows() omits `meta.aggregates` entirely when this
     * returns an empty array (contract: "meta assente per i domini senza
     * aggregati").
     *
     * @param  Builder<Model>  $query
     * @return array<string, int|float|string|null>
     */
    public function aggregates(Builder $query): array;

    /**
     * Whether this domain supports tree/hierarchical row scoping (spec
     * 0157, D-1) — the allow-list `TableRowsRequest` checks before
     * accepting `tree`/`treeParentId` in POST /tables/{domain}/rows: either
     * key sent to a domain that returns false here is a 422 (contract:
     * "nessun cambio di comportamento per gli altri domini"). Default
     * (AbstractTableDefinition): false.
     */
    public function supportsTree(): bool;

    /**
     * Narrow $query to root rows ($parentId === null) or the direct
     * children of $parentId (spec 0157, D-1). Called by
     * `TableService::rows()` AFTER every column/advanced filter and the
     * quick search are already applied, so tree mode combines with the rest
     * of the active query rather than replacing it (D-1: "con tutti i
     * filtri attivi"). Only ever reached when `supportsTree()` is true and
     * the request actually asked for tree mode; `$parentId`, when non-null,
     * has ALREADY been validated to exist (`TableRowsRequest`) and to be
     * visible to the actor (`TableController`, the 'view' Policy ability —
     * 403 otherwise). Default (AbstractTableDefinition): a no-op, never
     * reached for a domain that does not opt in.
     *
     * @param  Builder<Model>  $query
     */
    public function applyTreeScope(Builder $query, ?int $parentId): void;

    /**
     * Maximum rows returnable in a single SSRM block (`endRow - startRow`)
     * for POST /tables/{domain}/rows — the per-definition cap
     * `TableRowsRequest`/`TableService` enforce INSTEAD of the shared
     * `BaseApiController::MAX_LIMIT` (spec 0157: the Kanban view loads up
     * to 500 Task in one block). Default (AbstractTableDefinition):
     * `BaseApiController::MAX_LIMIT` — every domain keeps today's cap
     * unless it opts into a wider one.
     */
    public function maxRowsLimit(): int;
}
