<?php

declare(strict_types=1);

namespace App\Tables;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementService;
use App\Tables\RequestManagement\Concerns\WritesInlineEditableCells;
use App\Tables\RequestManagement\RequestAdvancedFilterCatalog;
use App\Tables\RequestManagement\RequestClientColumns;
use App\Tables\RequestManagement\RequestColumnCatalog;
use App\Tables\RequestManagement\RequestRelationColumns;
use App\Tables\RequestManagement\RequestRowMapper;
use App\Tables\Shared\OperationalSiteColumn;
use App\Tables\Shared\ProductsOfInterestColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `request-management` domain (spec 0049): the
 * "Gestione Richieste" operative view over `opportunities` (D-1, no new
 * entity, no new controller/route — ADR 0002). Access runs through the
 * module's OWN dedicated permission set (`request-management.*`, D-2), never
 * `opportunities.*` — `modelClass()` is Opportunity ONLY because that is
 * what the row IS, not because its Policy governs this domain.
 *
 * Two deviations from a plain relation-derived definition:
 *  - `authorizeViewAny()` is OVERRIDDEN: the AbstractTableDefinition fail-safe
 *    default would resolve `Gate::allows('viewAny', Opportunity::class)` →
 *    OpportunityPolicy → `opportunities.viewAny`, the WRONG permission for
 *    this domain. A direct `request-management.viewAny` permission check
 *    replaces it (fail-closed: no permission registered → false, never
 *    fail-open).
 *  - `baseQuery()` scopes to the actor's own managed opportunities (D-3,
 *    `opportunity_user` pivot) UNLESS they hold `request-management.viewAll`,
 *    mirroring LeadImportsTableDefinition's `Auth::id()` scoping precedent
 *    (authorizeViewAny runs first; a null id simply matches no rows via
 *    `whereHas`, never fail-open).
 *
 * `workflow_status`/`source`/`product_categories` are delegated to
 * RequestRelationColumns (file-size split, engineering.md §6). Spec 0056:
 * `operational_site` (the Sede operativa) has no relation-by-name equivalent
 * (the site has no own name) — delegated instead to the shared
 * App\Tables\Shared\OperationalSiteColumn.
 */
class RequestManagementTableDefinition extends AbstractTableDefinition
{
    use WritesInlineEditableCells;

    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The `workflow_status` advanced filter's name (RequestAdvancedFilterCatalog):
     * a SET filter matched by the related row's `name`, not by id — no
     * `opportunity-workflow-statuses/for-select` route exists to back an
     * id-based Relation/AsyncSearch picker.
     */
    private const string WORKFLOW_STATUS_ADVANCED_FILTER = 'workflow_status';

    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    private const string OPERATIONAL_SITE_RELATION = 'operationalSite';

    public function __construct(
        private readonly RequestRowMapper $rowMapper,
        private readonly RequestManagementService $service,
        private readonly RequestClientColumns $clientColumns,
        private readonly OperationalSiteColumn $operationalSiteColumn,
        private readonly RequestRelationColumns $relationColumns,
    ) {}

    /**
     * Global quick-search (spec 0009) over the client's anagraphic columns:
     * all DERIVED (no real `opportunities` column), hence delegated to
     * RequestClientColumns. Any other searchable column would fall through to
     * the generic engine (none today).
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->clientColumns->applySearch($query, $columnId, $pattern);
    }

    public function domain(): string
    {
        return 'request-management';
    }

    /**
     * @return class-string<Opportunity>
     */
    public function modelClass(): string
    {
        return Opportunity::class;
    }

    /**
     * Dedicated permission check (see class docblock): NEVER delegates to
     * OpportunityPolicy — `request-management` is a separate permission set
     * (D-2), independent of `opportunities.viewAny`.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return $actor->can('request-management.viewAny');
    }

    /**
     * Same deviation as authorizeViewAny() (spec 0053, D-4): the
     * AbstractTableDefinition fail-safe default would resolve
     * `Gate::allows('update', $row)` → OpportunityPolicy → `opportunities.update`,
     * the WRONG permission for this domain. `baseQuery()`'s own D-3 scoping
     * already keeps an out-of-scope row a 404 before this is ever reached.
     * The editable columns here today (spec 0054: `workflow_status` and
     * `next_callback_at`; spec 0055: `operator_ga2` plus the four client
     * anagraphic fields; user directives 2026-07-23/2026-07-31:
     * `products_of_interest`, `operational_site` and `source`) are each gated
     * per FIELD on top of this by the role_field_permissions matrix.
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return $actor->can('request-management.update');
    }

    /**
     * Same deviation as authorizeViewAny()/authorizeUpdate(), for the bulk
     * delete (user directive 2026-07-23): the default would resolve
     * OpportunityPolicy → `opportunities.delete`, a permission of a DIFFERENT
     * module. `baseQuery()`'s D-3 scoping already excluded every row the actor
     * does not manage before this is reached, so a scoped-out id is reported
     * `not_found`, never deleted.
     */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return $actor->can('request-management.delete');
    }

    // updateCell()/optionsFor() (spec 0054, D-4/D-5) live in
    // WritesInlineEditableCells (file-size budget, engineering.md §6).

    /**
     * @return Builder<Opportunity>
     */
    public function baseQuery(): Builder
    {
        $query = Opportunity::query()->with([
            // Spec 0075: the `product_categories` column projects the WHOLE
            // pair (funzione aziendale + categoria), so both relations are
            // eager-loaded — the cell renders the categories, the inline
            // editor reads the functions.
            'workflowStatus', 'productLines.productCategory', 'productLines.businessFunction',
            // Spec 0047 amendment 2026-07-27: RequestRowMapper::
            // allowedWorkflowStatusIds() calls OpportunityWorkflowResolver::
            // resolve() PER ROW, whose own step 1 loadMissing()s this
            // relation for a custom relation criterion (AC-032) — eager-load
            // it here so that loadMissing() stays a no-op across the whole
            // page instead of one query per row.
            'customFieldValueRow',
            // User directive 2026-07-23: the "Prodotti di interesse" column's
            // own `{id, name}` refs (cell + multiselect editor selection).
            'productsOfInterest',
            // User directive 2026-07-31: the "Fonte" column's own `{id, name}`
            // ref (cell + relation editor selection).
            'source',
            // `managers.avatar` so the "Operatore" (GA2) column can project the
            // inline avatar for the shared UserCell without a per-row query.
            'managers.avatar',
            // The client anagraphic columns (nome/cognome/codice fiscale/telefono)
            // are read from the Registry's PersonalData card + its primary
            // contacts, all resolved from memory in mapRow (no N+1).
            'registry.personalData.contacts',
            // Spec 0056: operationalSite's address+city for the composed
            // label (site has no own name).
            'operationalSite.addresses.city',
        ])
            // Per-row count for the `documents` action badge (HasAttachments),
            // scoped to the 'documents' collection only — never other
            // collections (mirrors OpportunitiesTableDefinition).
            ->withCount(['attachments as documents_count' => fn (Builder $q) => $q->where('collection', 'documents')])
            // Per-row count for the `notes` action badge (spec 0052 B4c,
            // HasNotes): roots AND replies together (the whole discussion),
            // soft-deleted notes excluded automatically by Note's own
            // SoftDeletes global scope — a single aggregated query, no N+1.
            ->withCount('notes')
            // Spec 0078, AC-037: the `pending_change_requests` badge column
            // counts only the STILL-OPEN requests on the record (D-4/D-5) —
            // `pendingFieldChangeRequests` (HasFieldChangeRequests) already
            // filters to `status = pending`, so an approved/rejected request
            // never inflates the count. A plain withCount, no raw subquery.
            ->withCount('pendingFieldChangeRequests');

        // D-3 scoping: only the opportunities where the actor is the GA2
        // "Operatore" (pivot position 2), unless they hold the viewAll ability.
        // `Auth::id()` is always set here (authorizeViewAny runs first and
        // requires auth); a null id would simply match no rows (never
        // fail-open) — mirrors LeadImportsTableDefinition's docblock reasoning.
        if (! Auth::user()?->can('request-management.viewAll')) {
            $query->whereHas('managers', static function (Builder $relatedQuery): void {
                $relatedQuery->where('users.id', Auth::id())
                    ->where('opportunity_user.position', Opportunity::OPERATOR_MANAGER_POSITION);
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RequestColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RequestColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RequestColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return RequestAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map an Opportunity to the operative row payload (delegated to
     * RequestRowMapper, so this definition keeps a single concern: query
     * building). `actions` is attached by the generic TableService via
     * actionsFor(); `documents_count`/`notes_count` ride along from
     * baseQuery's withCount.
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Opportunity $row */
        return [
            ...$this->rowMapper->map($row),
            'documents_count' => (int) ($row->documents_count ?? 0),
            'notes_count' => (int) ($row->notes_count ?? 0),
        ];
    }

    /**
     * `view` ("Lavora"), `notes` (spec 0052 B4b), `documents`, `delete` (user
     * directive 2026-07-23, gated by this module's OWN
     * `request-management.delete` — see authorizeDelete()) and `activity` —
     * `view` and `notes` gated by the SAME `request-management.view` (D-6:
     * reading a record's notes is inherited from the ability to open the
     * record, no separate notes permission), never OpportunityPolicy:
     * `Gate::allows('view', $row)` would resolve OpportunityPolicy
     * (`opportunities.view`), the wrong permission for this domain — same
     * reason `activity` reads `request-management.viewActivity` directly (the
     * endpoint re-checks it plus the GA2 scope via
     * RequestManagementActivityAuthorizer).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if ($actor->can('request-management.view')) {
            $allowed[] = 'view';
            $allowed[] = 'notes';
        }

        if ($actor->can('request-management.viewDocuments')) {
            $allowed[] = 'documents';
        }

        if ($actor->can('request-management.delete')) {
            $allowed[] = 'delete';
        }

        if ($actor->can('request-management.viewActivity')) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * `operational_site` (spec 0056) is delegated to the shared
     * OperationalSiteColumn (bound `line1` match); the four client anagraphic
     * columns (user directive 2026-08-03) to RequestClientColumns, which
     * re-points the generic FilterApplier at the PersonalData card inside a
     * `whereHas`; every other derived column falls through to
     * RequestRelationColumns' name-based `whereHas`.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter($query, self::OPERATIONAL_SITE_RELATION, $this->filterValues($filter));

            return true;
        }

        if ($columnId === ProductsOfInterestColumn::COLUMN_ID) {
            ProductsOfInterestColumn::applyFilter($query, $this->filterValues($filter));

            return true;
        }

        if ($this->clientColumns->applyFilter($query, $columnId, $columnConfig, $filter)) {
            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * `workflow_status`'s advanced filter (RequestAdvancedFilterCatalog) is a
     * SET filter matched by the related row's `name` (see the constant's
     * docblock) — the generic default (a plain `whereHas`-by-id for `type:
     * relation`/`async_search`) cannot express it. `operational_site` needs no
     * override: since it became an id-based picker (user directive
     * 2026-07-31) it IS a standard relation-by-id, like every other advanced
     * filter declared in the catalog — all handled by the generic default.
     * The COLUMN filter (`filterModel`, set-by-`line1`) is a different
     * surface and still goes through the shared OperationalSiteColumn, see
     * applyDerivedFilter().
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::WORKFLOW_STATUS_ADVANCED_FILTER) {
            $values = array_slice(array_values(array_filter(
                is_array($value) ? $value : [$value],
                static fn (mixed $item): bool => is_string($item) && $item !== '',
            )), 0, self::MAX_FILTER_VALUES);

            if ($values !== []) {
                $this->relationColumns->applyNameWhereHas($query, 'workflowStatus', $values);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        return is_array($values) ? array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )) : [];
    }

    /**
     * `operational_site` (spec 0056) is delegated to the shared
     * OperationalSiteColumn; the four client anagraphic columns (user
     * directive 2026-08-03) to RequestClientColumns' own correlated subquery
     * over the PersonalData card; `workflow_status` falls through to
     * RequestRelationColumns' correlated subquery sort. `product_categories`
     * (AGGREGATED to-many) is NOT sortable (no single related row to order
     * by).
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, 'opportunities', 'operational_site_id', $direction);

            return true;
        }

        if ($this->clientColumns->applySort($query, $columnId, $direction)) {
            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `operational_site` (spec
     * 0056) is delegated to the shared OperationalSiteColumn, the four client
     * anagraphic columns to RequestClientColumns (the card values of the rows
     * matching every OTHER active filter); every other derived column falls
     * through to RequestRelationColumns.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($query, 'operational_site_id', $search, $limit);
        }

        if ($columnId === ProductsOfInterestColumn::COLUMN_ID) {
            return ProductsOfInterestColumn::distinctValues($query, $search, $limit);
        }

        return $this->clientColumns->distinctValues($columnId, $query, $search, $limit)
            ?? $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }
}
