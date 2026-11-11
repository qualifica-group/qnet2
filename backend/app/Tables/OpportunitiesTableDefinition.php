<?php

namespace App\Tables;

use App\Enums\CategoryUsageArea;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Services\Opportunities\OpportunityVisibilityScope;
use App\Services\ProductCategories\UsageAreaRowFilter;
use App\Services\RequestManagement\RequestManagementScope;
use App\Tables\Opportunities\OpportunityAdvancedFilterCatalog;
use App\Tables\Opportunities\OpportunityCellWriter;
use App\Tables\Opportunities\OpportunityColumnCatalog;
use App\Tables\Opportunities\OpportunityRelationColumns;
use App\Tables\Opportunities\OpportunityStatusColumn;
use App\Tables\Shared\CommercialListFilterApplier;
use App\Tables\Shared\DocumentsCountColumn;
use App\Tables\Shared\OperationalSiteColumn;
use App\Tables\Shared\ProductLinePairsColumn;
use App\Tables\Shared\ProductsOfInterestColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `opportunities` domain (spec 0040).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real columns handled entirely by the
 * generic engine. `registry`/`referent`/`commercial`/`supervisor`/
 * `managers`/`product_category`/`business_function` are
 * all relation-derived columns delegated to OpportunityRelationColumns (file-
 * size split, engineering.md §6): own-FK simple relations, the `managers`
 * pivot and the 2 AGGREGATED (to-many, via `productLines`) columns.
 *
 * Spec 0082: `status` is COMPUTED from the row's quotes (or its working state
 * when it has none) by OpportunityStatusResolver — backed by no column at all,
 * so its filter and distinct values live in OpportunityStatusColumn and it is
 * never sortable.
 *
 * Spec 0056: `operational_site` is a SPECIALLY-derived column (the site has
 * no own name — the generic name-based whereIn/subquery machinery above
 * would be SQL-invalid against it), delegated instead to the shared
 * App\Tables\Shared\OperationalSiteColumn, mirroring LeadsTableDefinition's
 * own `operational_site` handling.
 */
class OpportunitiesTableDefinition extends AbstractTableDefinition
{
    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    private const string OPERATIONAL_SITE_RELATION = 'operationalSite';

    private const string OPPORTUNITIES_TABLE = 'opportunities';

    private const string OPERATIONAL_SITE_FK = 'operational_site_id';

    public function __construct(
        private readonly OperationalSiteColumn $operationalSiteColumn,
        private readonly OpportunityRelationColumns $relationColumns,
        private readonly OpportunityCellWriter $cellWriter,
        private readonly ProductLinePairsColumn $productLinePairs,
        private readonly OpportunityStatusResolver $statusResolver,
        private readonly CommercialListFilterApplier $listFilters,
    ) {}

    /**
     * Spec 0206, D-2: every editable column — `products_of_interest` and the
     * scalars included — writes through OpportunityCellWriter (the form's own
     * UpdateOpportunityRequest + OpportunityService), so the title writer,
     * the product coverage rule and the lead lock apply in the grid too.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        /** @var Opportunity $row */
        /** @var User $actor */
        $actor = Auth::user();

        return $this->cellWriter->write($row, $columnId, $value, $actor);
    }

    public function domain(): string
    {
        return 'opportunities';
    }

    /**
     * @return class-string<Opportunity>
     */
    public function modelClass(): string
    {
        return Opportunity::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives OpportunityPolicy::viewAny
    // from modelClass() (opportunities.viewAny).

    /**
     * @return Builder<Opportunity>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the page.
        // supervisor/managers pull their avatar relation too, so each row can
        // project the inline avatar (data URI) without a per-row query.
        // Spec 0316: rows, totals, filters, export, inline edit and bulk delete
        // all start here, so the visibility rule lives in one place.
        // Spec 0389 rev.2: opportunities whose categories are all reserved to Gestione Richieste are not listed.
        $query = UsageAreaRowFilter::apply(OpportunityVisibilityScope::scopeToActor(Opportunity::query(), Auth::user()), 'opportunities.id', CategoryUsageArea::Opportunities)
            ->with([
                'registry', 'referent', 'commercial', 'supervisor.avatar',
                'managers.avatar', 'productLines.businessFunction', 'productLines.productCategory',
                // Spec 0082: the computed `status` cell — eager-loaded so the
                // resolver stays query-free across the page (AC-007).
                ...OpportunityStatusResolver::EAGER_LOADS,
                // User directive 2026-07-23: the "Prodotti di interesse"
                // column projects its own `{id, name}` refs (the cell AND the
                // multiselect editor's current selection).
                'productsOfInterest',
                // Spec 0056: operationalSite's address+city for the composed
                // label (site has no own name, mirrors LeadsTableDefinition).
                'operationalSite.addresses.city',
            ])
            // Per-row count for the `documents` action badge (spec 0363 D-4).
            ->withCount(DocumentsCountColumn::own())
            // Per-row count for the `notes` action badge (HasNotes): roots AND
            // replies, soft-deleted excluded — same projection Gestione
            // Richieste carries.
            ->withCount('notes');

        // Spec 0105 D-10, spec 0340: whether one of the row's Offerte is in the
        // actor's Gestione Richieste scope through the site or competence tier.
        // The rule is on the OFFERTE (RequestManagementScope::scopeToActor(),
        // never re-spelled here) and this list eager-loads no quotes, hence one
        // EXISTS subquery for the whole page instead of a query per row. Added
        // only for an actor who can use it: allowsNotes() reads it as absent.
        $actor = Auth::user();

        if ($actor !== null && $this->widensRequestScope($actor)) {
            $query->withExists([
                'quotes as request_scoped_quote_exists' => fn (Builder $quotes): Builder => RequestManagementScope::scopeToActor($quotes, $actor),
            ]);
        }

        return $query;
    }

    /**
     * Whether the actor is scoped to some Offerte without seeing them all
     * (team, site or competence tier): the only case where the EXISTS above
     * can change allowsNotes()' verdict.
     */
    private function widensRequestScope(User $actor): bool
    {
        return $actor->can('request-management.view') && ! $actor->can('request-management.viewAll');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return OpportunityColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return OpportunityColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return OpportunityColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [...$this->listFilters->presetFilters('opportunities', Auth::user(), OpportunityVisibilityScope::seesBeyondOwnRecords(Auth::user()), CategoryUsageArea::Opportunities), ...OpportunityAdvancedFilterCatalog::advancedFilters()];
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
     * Map an Opportunity to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Opportunity $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'registry' => $this->summarize($row->registry),
            'referent' => $this->summarize($row->referent),
            'commercial' => $this->summarize($row->commercial),
            'supervisor' => $this->userSummary($row->supervisor),
            'managers' => $row->managers->map(fn (User $user): array => $this->userSummary($user))->all(),
            'operational_site' => $this->operationalSiteColumn->summarize($row->operationalSite),
            OpportunityStatusColumn::COLUMN_ID => $this->statusResolver->resolve($row),
            // Spec 0206, D-9: the pairs the `product_lines` editor edits.
            'product_category' => $this->productLinePairs->project($row),
            'business_function' => $this->summarizeNames($row->productLines->pluck('businessFunction')),
            ...ProductsOfInterestColumn::project($row),
            'estimated_value' => $row->estimated_value,
            'success_probability' => $row->success_probability,
            'start_date' => $row->start_date,
            'expected_close_date' => $row->expected_close_date,
            'created_at' => $row->created_at,
            DocumentsCountColumn::FIELD => DocumentsCountColumn::project($row),
            'notes_count' => (int) ($row->notes_count ?? 0),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A person summary carrying the inline avatar (data URI) so the supervisor
     * and managers columns render a real avatar, not just initials — mirrors
     * BusinessFunctionsTableDefinition::userSummary(). Null when unset.
     *
     * @return array{id: int, name: string, avatar_url: string|null}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatarDataUri(),
        ];
    }

    /**
     * Display value for an AGGREGATED to-many column (amendment rev.3): the
     * distinct related names, comma-joined — null when there is none.
     *
     * @param  Collection<int, Model|null>  $related
     */
    private function summarizeNames(Collection $related): ?string
    {
        $names = $related->filter()->pluck('name')->unique()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * Allowed action keys for a single row, via OpportunityPolicy — except
     * `notes`, whose thread is registered under the `request-management`
     * entity_type (RequestManagementNotable): its gate is that module's own
     * permission set, never OpportunityPolicy. `edit` is gone as a row action
     * (user directive 2026-08-05): editing starts from the detail surface,
     * gated there by `opportunities.update`, which the update endpoint
     * re-checks.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        /** @var Opportunity $row */
        if ($this->allowsNotes($actor, $row)) {
            $allowed[] = 'notes';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        if (Gate::forUser($actor)->allows('viewDocuments', $row)) {
            $allowed[] = 'documents';
        }

        return $allowed;
    }

    /**
     * The SAME rule RequestManagementNotable::authorizeRead applies when the
     * note endpoints re-check the thread: `request-management.view` AND one of
     * `request-management.viewAll`, being that opportunity's GA2 Operatore, or
     * reaching one of its Offerte through the team (spec 0373) or `viewSite`
     * (spec 0105).
     * The full rule is evaluated here — unlike the request-management table,
     * this list is NOT already scoped to the actor's own opportunities, so
     * checking only `request-management.view` would offer the action on rows
     * whose thread the actor cannot read (a 403 inside the dialog). Reads the
     * eager-loaded `managers` collection via `operatorManager()`, the single
     * expression of the "position 2 = operator" rule: no per-row query.
     *
     * Spec 0105, spec 0340, spec 0373: the team, site and competence tiers read
     * `request_scoped_quote_exists`, the EXISTS flag baseQuery() projects for
     * a scoped actor — absent (hence false) otherwise.
     */
    private function allowsNotes(User $actor, Opportunity $row): bool
    {
        if (! $actor->can('request-management.view')) {
            return false;
        }

        return $actor->can('request-management.viewAll')
            || $row->operatorManager()?->id === $actor->id
            || (bool) ($row->request_scoped_quote_exists ?? false);
    }

    /**
     * `operational_site` (spec 0056) has no relation-by-id equivalent (the
     * site has no own name) — the generic default (which delegates a
     * `relation` type to a plain whereHas-by-id) cannot express it. Every
     * other advanced filter declared in OpportunityAdvancedFilterCatalog is a
     * standard relation-by-id, handled by the generic default.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        $applyCategories = static function (Builder $opportunities, array $categoryIds): void {
            $opportunities->whereIn('opportunities.id', static fn (QueryBuilder $lines) => $lines
                ->select('opportunity_id')
                ->from('opportunity_product_lines')
                ->whereIn('product_category_id', $categoryIds));
        };

        if ($this->listFilters->apply($query, $name, $value, Auth::user(), $applyCategories)) {
            return true;
        }

        if ($name === self::OPERATIONAL_SITE_COLUMN) {
            if (is_string($value) && $value !== '') {
                $this->operationalSiteColumn->applyAdvancedFilter($query, self::OPERATIONAL_SITE_RELATION, $value);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * `operational_site` (spec 0056) is delegated to the shared
     * OperationalSiteColumn (bound `line1` match, never the name-based
     * whereIn OpportunityRelationColumns applies); every other derived column
     * falls through to it.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter(
                $query,
                self::OPERATIONAL_SITE_RELATION,
                $this->filterValues($filter),
                $this->matchesBlankEntry($filter),
            );

            return true;
        }

        if ($columnId === ProductsOfInterestColumn::COLUMN_ID) {
            ProductsOfInterestColumn::applyFilter($query, $this->filterValues($filter), $this->matchesBlankEntry($filter));

            return true;
        }

        if ($columnId === OpportunityStatusColumn::COLUMN_ID) {
            OpportunityStatusColumn::applyFilter($query, $this->filterValues($filter));

            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
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
     * @param  Builder<Opportunity>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->relationColumns->applySearch($query, $columnId, $pattern);
    }

    /**
     * `operational_site` (spec 0056) is delegated to the shared
     * OperationalSiteColumn; every other simple-relation column falls
     * through to OpportunityRelationColumns' correlated subquery sort.
     *
     * @param  Builder<Opportunity>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, self::OPPORTUNITIES_TABLE, self::OPERATIONAL_SITE_FK, $direction);

            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `operational_site` (spec
     * 0056) is delegated to the shared OperationalSiteColumn; every other
     * derived column falls through to OpportunityRelationColumns.
     *
     * @param  Builder<Opportunity>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($query, self::OPERATIONAL_SITE_FK, self::OPERATIONAL_SITE_RELATION, $search, $limit);
        }

        if ($columnId === ProductsOfInterestColumn::COLUMN_ID) {
            return ProductsOfInterestColumn::distinctValues($query, $search, $limit);
        }

        if ($columnId === OpportunityStatusColumn::COLUMN_ID) {
            return OpportunityStatusColumn::distinctValues($query, $search, $limit);
        }

        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }
}
