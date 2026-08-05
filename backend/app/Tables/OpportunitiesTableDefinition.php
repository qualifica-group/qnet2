<?php

namespace App\Tables;

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\OpportunityProductInterestWriter;
use App\Services\Opportunities\OpportunityStatusResolver;
use App\Tables\Opportunities\OpportunityAdvancedFilterCatalog;
use App\Tables\Opportunities\OpportunityColumnCatalog;
use App\Tables\Opportunities\OpportunityRelationColumns;
use App\Tables\Opportunities\OpportunityStatusColumn;
use App\Tables\Shared\OperationalSiteColumn;
use App\Tables\Shared\ProductsOfInterestColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `opportunities` domain (spec 0040).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real columns handled entirely by the
 * generic engine. `registry`/`referent`/`commercial`/`supervisor`/`source`/
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
        private readonly OpportunityProductInterestWriter $productInterestWriter,
        private readonly OpportunityStatusResolver $statusResolver,
    ) {}

    /**
     * `products_of_interest` (user directive 2026-07-23) is a to-many
     * collection, not a column: the generic mass-assignment default would
     * fail, and a bare `sync()` would break the invariant that every selected
     * product's category is covered by a product line. It writes through the
     * SAME OpportunityProductInterestWriter both other channels use (the CRUD
     * service and the work panel), so the cross-category rule can never
     * diverge between them. Every other editable column keeps the default.
     */
    public function updateCell(Model $row, string $columnId, mixed $value): Model
    {
        if ($columnId !== ProductsOfInterestColumn::COLUMN_ID) {
            return parent::updateCell($row, $columnId, $value);
        }

        /** @var Opportunity $row */
        /** @var array<int, int> $value */
        $this->productInterestWriter->sync($row, $value);

        return $row->fresh() ?? $row;
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
        return Opportunity::query()
            ->with([
                'registry', 'referent', 'commercial', 'supervisor.avatar', 'source',
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
            // Per-row count for the `documents` action badge (HasAttachments),
            // scoped to the 'documents' collection only — never other collections.
            ->withCount(['attachments as documents_count' => fn (Builder $q) => $q->where('collection', 'documents')])
            // Per-row count for the `notes` action badge (HasNotes): roots AND
            // replies, soft-deleted excluded — same projection Gestione
            // Richieste carries.
            ->withCount('notes');
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
        return OpportunityAdvancedFilterCatalog::advancedFilters();
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
            'source' => $this->summarize($row->source),
            'operational_site' => $this->operationalSiteColumn->summarize($row->operationalSite),
            OpportunityStatusColumn::COLUMN_ID => $this->statusResolver->resolve($row),
            'product_category' => $this->summarizeNames($row->productLines->pluck('productCategory')),
            'business_function' => $this->summarizeNames($row->productLines->pluck('businessFunction')),
            ...ProductsOfInterestColumn::project($row),
            'estimated_value' => $row->estimated_value,
            'success_probability' => $row->success_probability,
            'start_date' => $row->start_date,
            'expected_close_date' => $row->expected_close_date,
            'created_at' => $row->created_at,
            'documents_count' => (int) ($row->documents_count ?? 0),
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
     * note endpoints re-check the thread: `request-management.view` AND either
     * `request-management.viewAll` or being that opportunity's GA2 Operatore.
     * The full rule is evaluated here — unlike the request-management table,
     * this list is NOT already scoped to the actor's own opportunities, so
     * checking only `request-management.view` would offer the action on rows
     * whose thread the actor cannot read (a 403 inside the dialog). Reads the
     * eager-loaded `managers` collection via `operatorManager()`, the single
     * expression of the "position 2 = operator" rule: no per-row query.
     */
    private function allowsNotes(User $actor, Opportunity $row): bool
    {
        if (! $actor->can('request-management.view')) {
            return false;
        }

        return $actor->can('request-management.viewAll')
            || $row->operatorManager()?->id === $actor->id;
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
            $this->operationalSiteColumn->applyFilter($query, self::OPERATIONAL_SITE_RELATION, $this->filterValues($filter));

            return true;
        }

        if ($columnId === ProductsOfInterestColumn::COLUMN_ID) {
            ProductsOfInterestColumn::applyFilter($query, $this->filterValues($filter));

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
            return $this->operationalSiteColumn->distinctValues($query, self::OPERATIONAL_SITE_FK, $search, $limit);
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
