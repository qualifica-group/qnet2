<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\AdvancedFilterType;
use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use App\Services\RequestManagement\RequestManagementService;
use App\Services\Table\AdvancedFilterApplier;
use App\Tables\RequestManagement\Concerns\WritesInlineEditableCells;
use App\Tables\RequestManagement\RequestActionCatalog;
use App\Tables\RequestManagement\RequestAdvancedFilterCatalog;
use App\Tables\RequestManagement\RequestClientColumns;
use App\Tables\RequestManagement\RequestColumnCatalog;
use App\Tables\RequestManagement\RequestRelationColumns;
use App\Tables\RequestManagement\RequestRowMapper;
use App\Tables\Shared\OfferLinesColumn;
use App\Tables\Shared\OperationalSiteColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Table definition for the `request-management` domain (spec 0086: the row
 * is now a `quotes` record, D-1 — migrated off the former
 * `opportunities`-rooted grid, spec 0049). Access runs through the module's
 * OWN dedicated permission set (`request-management.*`, D-2), never
 * `quotes.*`/`opportunities.*` — `modelClass()` is Quote ONLY because that is
 * what the row IS, not because QuotePolicy governs this domain. Fields that
 * live on the underlying Opportunity are read AND written through
 * `quote.opportunity` (D-2).
 *
 * Two deviations from a plain relation-derived definition:
 *  - `authorizeViewAny()` is OVERRIDDEN: the AbstractTableDefinition fail-safe
 *    default would resolve `Gate::allows('viewAny', Quote::class)` ->
 *    QuotePolicy -> `quotes.viewAny`, the WRONG permission for this domain. A
 *    direct `request-management.viewAny` permission check replaces it
 *    (fail-closed: no permission registered -> false, never fail-open).
 *  - `baseQuery()` scopes to the actor's own supervised offers
 *    (`quotes.supervisor_id`, D-3) UNLESS they hold
 *    `request-management.viewAll` — delegated to
 *    `App\Services\RequestManagement\RequestManagementScope::scopeToActor()`,
 *    THE single implementation of this rule shared by every one of the six
 *    callers the spec enumerates (context, "Scoping non-supervisore riscritto
 *    ... in tutti e 6 i punti"). Fail-closed by construction: a null actor
 *    degrades to an always-empty result, never to "sees everything".
 *
 * The category tabs' row scope (spec 0064 D-2/D-3) and the GA2 operator
 * relabel (spec 0080) are NOT here: spec 0084 moved them into the decorator
 * `App\Tables\RequestManagement\RequestManagementScopedTableDefinition`,
 * composed OUTSIDE `CustomFieldAwareTableDefinition` in
 * `TableRegistry::resolve()` — this domain IS custom-fieldable
 * (`CustomFieldEntityRegistry`), so `TableController`'s `instanceof` scoping
 * check must target the OUTERMOST wrap, exactly like
 * `App\Tables\Quotes\OpportunityScopedTableDefinition` does for `quotes`; a
 * capability baked into THIS concrete class would sit one layer too deep and
 * never be reached once wrapped.
 *
 * `source`/`product_categories`/`general_notes`/`next_callback_at` are
 * delegated to RequestRelationColumns (file-size split, engineering.md §6);
 * the four client anagraphic columns to RequestClientColumns. `operator_ga2`
 * is NOT sortable/filterable (AC-011 corrected in execution: this migration
 * moves only the underlying model, never filter/sort behaviour — see
 * RequestRelationColumns' class docblock). Spec 0056: `operational_site`
 * (the Sede operativa) —
 * spec 0086, D-6: a real FK on `quotes` itself now, unchanged in shape — has
 * no relation-by-name equivalent (the site has no own name), delegated
 * instead to the shared App\Tables\Shared\OperationalSiteColumn.
 * `offer_lines` (spec 0086, D-7) is delegated to the shared
 * App\Tables\Shared\OfferLinesColumn, replacing `products_of_interest` on
 * this domain only (AC-009: `opportunities` keeps its own untouched
 * ProductsOfInterestColumn).
 */
class RequestManagementTableDefinition extends AbstractTableDefinition
{
    use WritesInlineEditableCells;

    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    private const string OPERATIONAL_SITE_RELATION = 'operationalSite';

    /**
     * AC-013: the two DateRange advanced filters whose real column lives on
     * `opportunities`, not `quotes` — applyAdvancedFilter() below scopes them
     * inside a `whereHas('opportunity', ...)` closure instead of the generic
     * default's plain `$query->where($target, ...)`.
     *
     * @var array<string, string>
     */
    private const array OPPORTUNITY_RANGE_ADVANCED_FILTERS = [
        'expected_close_range' => 'expected_close_date',
        'next_callback_range' => 'next_callback_at',
    ];

    public function __construct(
        private readonly RequestRowMapper $rowMapper,
        private readonly RequestManagementService $service,
        private readonly RequestClientColumns $clientColumns,
        private readonly OperationalSiteColumn $operationalSiteColumn,
        private readonly RequestRelationColumns $relationColumns,
    ) {}

    /**
     * Global quick-search (spec 0009) over the client's anagraphic columns:
     * all DERIVED (no real `quotes` column), hence delegated to
     * RequestClientColumns. Any other searchable column would fall through to
     * the generic engine (none today).
     *
     * @param  Builder<Quote>  $query
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
     * @return class-string<Quote>
     */
    public function modelClass(): string
    {
        return Quote::class;
    }

    /**
     * Dedicated permission check (see class docblock): NEVER delegates to
     * QuotePolicy — `request-management` is a separate permission set (D-2),
     * independent of `quotes.viewAny`.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return $actor->can('request-management.viewAny');
    }

    /**
     * Same deviation as authorizeViewAny() (spec 0053, D-4): the
     * AbstractTableDefinition fail-safe default would resolve
     * `Gate::allows('update', $row)` -> QuotePolicy (`quotes.update`), the
     * WRONG permission for this domain. `baseQuery()`'s own D-3 scoping
     * already keeps an out-of-scope row a 404 before this is ever reached.
     * The editable columns here today (spec 0054: `next_callback_at`; spec
     * 0055: `operator_ga2` plus the four client anagraphic fields; user
     * directives 2026-07-23/2026-07-31: `offer_lines` is NOT among them
     * — AC-021/AC-022 —, `operational_site` and `source`) are each gated per
     * FIELD on top of this by the role_field_permissions matrix.
     */
    public function authorizeUpdate(User $actor, Model $row): bool
    {
        return $actor->can('request-management.update');
    }

    /**
     * Same deviation as authorizeViewAny()/authorizeUpdate(), for the bulk
     * delete (user directive 2026-07-23): the default would resolve
     * QuotePolicy -> `quotes.delete`, a permission of a DIFFERENT module.
     * `baseQuery()`'s D-3 scoping already excluded every row the actor does
     * not manage before this is reached, so a scoped-out id is reported
     * `not_found`, never deleted.
     */
    public function authorizeDelete(User $actor, Model $row): bool
    {
        return $actor->can('request-management.delete');
    }

    // updateCell()/optionsFor() (spec 0054, D-4/D-5) live in
    // WritesInlineEditableCells (file-size budget, engineering.md §6).

    /**
     * @return Builder<Quote>
     */
    public function baseQuery(): Builder
    {
        $query = Quote::query()->with([
            // `source`/`product_categories`/`general_notes`/`next_callback_at`/
            // the client anagraphic columns (spec 0086, D-2): nested
            // dot-paths eager-load the WHOLE `opportunity` record in one
            // shot, not just the leaf relation.
            'opportunity.source',
            'opportunity.productLines.productCategory', 'opportunity.productLines.businessFunction',
            'opportunity.registry.personalData.contacts',
            // `supervisor.avatar` (D-3): the "Operatore" (GA2) column's
            // inline avatar for the shared UserCell, no per-row query.
            'supervisor.avatar',
            // Spec 0056/0086, D-6: operationalSite's address+city for the
            // composed label (site has no own name) — now the OFFER's own FK.
            'operationalSite.addresses.city',
            // "Linee di prodotto" (spec 0086, D-7): the offer's own REVENUE
            // lines' products (App\Tables\Shared\OfferLinesColumn). The
            // `.category` hop and `opportunity.customFieldValueRow` below are
            // what QuoteWorkflowResolver reads to resolve each row's own
            // destination set (RequestRowMapper's
            // `quote_workflow_status_options`): eager-loaded here so its
            // `loadMissing()` is a no-op and the page costs no per-row query.
            'offerLines.product.category',
            'opportunity.customFieldValueRow',
            // "Stato di lavorazione" (user directive 2026-08-31): the OFFER's
            // own working state, a real FK on `quotes` — eager-loaded so the
            // badge cell and its inline select never fire a per-row query.
            'quoteWorkflowStatus',
            // The single-record GET/PATCH response's `transferred_from`
            // reuses this SAME baseQuery() (mirrors D-10's
            // `FieldChangeRequestValueResolver::record()` precedent).
            'transferredFromOperationalSite',
        ])
            // D-9: documents stay anchored to the Opportunity — a correlated
            // subquery through `quotes.opportunity_id`, since `withCount()`
            // cannot span the BelongsTo `opportunity` hop.
            ->addSelect([
                'documents_count' => Attachment::query()
                    ->selectRaw('count(*)')
                    ->where('attachable_type', (new Opportunity)->getMorphClass())
                    ->where('collection', 'documents')
                    ->whereColumn('attachable_id', 'quotes.opportunity_id'),
            ])
            // Badge dell'azione `notes` (direttiva utente 2026-08-07): le note
            // SCOPATE a questa Offerta (`notes.quote_id`), non l'intero thread
            // dell'Opportunita' — esattamente il thread che l'azione apre, come
            // gia' fa QuotesTableDefinition. Roots e reply insieme;
            // soft-deleted escluse dal global scope di Note.
            ->withCount(['scopedNotes as notes_count'])
            // Spec 0078, AC-037; spec 0086, D-10 (corrected in execution):
            // the OFFER's OWN still-open requests — two sibling offers carry
            // independent badges.
            ->withCount('pendingFieldChangeRequests');

        // D-3 scoping: THE single implementation of "solo le mie righe"
        // (RequestManagementScope), fail-closed by construction.
        return RequestManagementScope::scopeToActor($query, Auth::user());
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
        return RequestActionCatalog::actions();
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
            // AC-014: `quotes.created_at` — a real column on the row's own
            // table now, no derived hook needed.
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
     * Map a Quote to the operative row payload (delegated to
     * RequestRowMapper, so this definition keeps a single concern: query
     * building). `actions` is attached by the generic TableService via
     * actionsFor(); `documents_count` rides along from baseQuery's addSelect
     * (D-9: still counted through the OFFER's opportunity) and `notes_count`
     * from its `scopedNotes` withCount (the OFFER's own notes).
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Quote $row */
        return [
            ...$this->rowMapper->map($row),
            'documents_count' => (int) ($row->documents_count ?? 0),
            'notes_count' => (int) ($row->notes_count ?? 0),
        ];
    }

    /**
     * `view` ("Lavora"), `notes` (spec 0052 B4b), `documents`,
     * `transfer-contact` (spec 0079, gated by its OWN
     * `request-management.transferContact`), `delete` (user directive
     * 2026-07-23, gated by this module's OWN `request-management.delete` —
     * see authorizeDelete()) and `activity` — `view` and `notes` gated by
     * the SAME `request-management.view` (D-6: reading a record's notes is
     * inherited from the ability to open the record, no separate notes
     * permission), never QuotePolicy: `Gate::allows('view', $row)` would
     * resolve QuotePolicy (`quotes.view`), the wrong permission for this
     * domain — same reason `activity` reads `request-management.viewActivity`
     * directly (the endpoint re-checks it plus the D-3 supervisor scope via
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

        if ($actor->can('request-management.transferContact')) {
            $allowed[] = 'transfer-contact';
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
     * `operational_site` (spec 0056/0086 D-6) is delegated to the shared
     * OperationalSiteColumn (bound `line1` match); `offer_lines` (spec 0086,
     * D-7) to the shared OfferLinesColumn; the four client anagraphic
     * columns (user directive 2026-08-03) to RequestClientColumns, which
     * re-points the generic FilterApplier at the PersonalData card inside a
     * `whereHas`; every other derived column falls through to
     * RequestRelationColumns.
     *
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter($query, self::OPERATIONAL_SITE_RELATION, $this->filterValues($filter));

            return true;
        }

        if ($columnId === OfferLinesColumn::COLUMN_ID) {
            OfferLinesColumn::applyFilter($query, $this->filterValues($filter));

            return true;
        }

        if ($this->clientColumns->applyFilter($query, $columnId, $columnConfig, $filter)) {
            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $columnConfig, $filter);
    }

    /**
     * AC-013: `expected_close_range`/`next_callback_range` target a real
     * `opportunities` column (RequestAdvancedFilterCatalog docblock) — the
     * generic default's plain `$query->where($target, ...)` would target a
     * column that does not exist on `quotes`, so both are scoped inside a
     * `whereHas('opportunity', ...)` closure instead, reusing the SAME
     * AdvancedFilterApplier the generic default itself calls (DRY: the exact
     * date-range operator set, no reimplementation). Every other advanced
     * filter (id-based `relation`/`async_search`, including `registry`/
     * `referent`'s NEW `opportunity.` dot-path targets) needs no override:
     * Eloquent's own `whereHas()` already supports nested relations.
     *
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        $opportunityColumn = self::OPPORTUNITY_RANGE_ADVANCED_FILTERS[$name] ?? null;

        if ($opportunityColumn === null) {
            return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
        }

        $type = $descriptor['type'] ?? null;

        if ($type instanceof AdvancedFilterType) {
            $query->whereHas('opportunity', function (Builder $opportunityQuery) use ($type, $opportunityColumn, $value, $descriptor): void {
                app(AdvancedFilterApplier::class)->apply($opportunityQuery, $type, $opportunityColumn, $value, $descriptor);
            });
        }

        return true;
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
     * `operational_site` (spec 0056/0086 D-6) is delegated to the shared
     * OperationalSiteColumn, correlated against `quotes` itself (the FK
     * moved there); the four client anagraphic columns to RequestClientColumns'
     * own correlated subquery; `source`/`general_notes`/`next_callback_at`
     * fall through to RequestRelationColumns. `product_categories`/
     * `offer_lines`/`operator_ga2` are NOT sortable (the first two: no single
     * related row to order by; `operator_ga2`: AC-011 corrected in execution
     * — unchanged from before this migration).
     *
     * @param  Builder<Quote>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, 'quotes', 'operational_site_id', $direction);

            return true;
        }

        if ($this->clientColumns->applySort($query, $columnId, $direction)) {
            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `operational_site` and
     * `offer_lines` are delegated to their own shared column classes, the
     * four client anagraphic columns to RequestClientColumns (the card
     * values of the rows matching every OTHER active filter); every other
     * derived column falls through to RequestRelationColumns.
     * `general_notes`/`next_callback_at` are never reached here
     * (`hasFilterValues: false`, RequestColumnCatalog).
     *
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($query, 'operational_site_id', $search, $limit);
        }

        if ($columnId === OfferLinesColumn::COLUMN_ID) {
            return OfferLinesColumn::distinctValues($query, $search, $limit);
        }

        return $this->clientColumns->distinctValues($columnId, $query, $search, $limit)
            ?? $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }
}
