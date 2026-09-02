<?php

namespace App\Tables;

use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Services\QuoteService;
use App\Tables\Quotes\QuoteAdvancedFilterCatalog;
use App\Tables\Quotes\QuoteColumnCatalog;
use App\Tables\Quotes\QuoteRelationColumns;
use App\Tables\Shared\OperationalSiteColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `quotes` domain (spec 0065, MT-05).
 *
 * `code`/`title`/`created_at` and the 3 persisted header aggregates
 * (`revenue_net`/`cost_net`/`margin_net`, D-9) are real DB columns handled
 * entirely by the generic engine. `opportunity`/`quote_workflow_status`/
 * `commercial`/`reporter`/`supervisor` are relation-derived columns delegated to
 * QuoteRelationColumns (file-size split, engineering.md §6): own-FK simple
 * relations, mirroring OpportunitiesTableDefinition.
 *
 * User directive 2026-07-30 adds three more: `company`/`company_site` join
 * the QuoteRelationColumns set, while `operational_site` is SPECIALLY derived
 * (the site has no label column — its identity is its primary address) and is
 * handled here against the shared OperationalSiteColumn, exactly as
 * OpportunitiesTableDefinition does.
 */
class QuotesTableDefinition extends AbstractTableDefinition
{
    /** The specially-derived site column (no label column of its own). */
    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    private const string OPERATIONAL_SITE_RELATION = 'operationalSite';

    private const string OPERATIONAL_SITE_FK = 'operational_site_id';

    private const string QUOTES_TABLE = 'quotes';

    /**
     * The `quote_workflow_status` advanced filter's name
     * (QuoteAdvancedFilterCatalog): a SET filter matched by the related
     * row's `name`, not by id — no `quote-workflow-statuses/for-select`
     * route exists to back an id-based Relation/AsyncSearch picker.
     */
    private const string WORKFLOW_STATUS_ADVANCED_FILTER = 'quote_workflow_status';

    /** Maximum number of names honoured in the advanced filter (caps the WHERE IN cardinality, defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(
        private readonly QuoteRelationColumns $relationColumns,
        private readonly OperationalSiteColumn $operationalSiteColumn,
        private readonly QuoteService $service,
    ) {}

    public function domain(): string
    {
        return 'quotes';
    }

    /**
     * @return class-string<Quote>
     */
    public function modelClass(): string
    {
        return Quote::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives QuotePolicy::viewAny from
    // modelClass() (quotes.viewAny).

    /**
     * @return Builder<Quote>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the page.
        // supervisor/managers pull their avatar relation too, so the row can
        // project the inline avatar (data URI) without a per-row query —
        // mirrors OpportunitiesTableDefinition's own supervisor.avatar/
        // managers.avatar eager-loads. Spec 0087, D-10: the `notes` action
        // gate now reads the OFFERTA's own `operator_id` column directly (no
        // relation to eager-load for it any more — was `opportunity.managers`
        // before this migration).
        return Quote::query()->with([
            'opportunity', 'quoteWorkflowStatus', 'commercial', 'reporter', 'supervisor.avatar',
            // "Gestori Account" (spec 0087, D-1/T-10): the Offerta's own team,
            // rendered as an avatar stack by the `managers` column.
            'managers.avatar',
            'company', 'companySite',
            // The site has no own name: the composed label needs its primary
            // address + city (mirrors OpportunitiesTableDefinition).
            'operationalSite.addresses.city',
        ])
            // Per-row count for the `notes` action badge: the notes SCOPED to
            // this Offerta (`notes.quote_id`), not the parent Opportunity's
            // whole thread — the dialog this action opens shows exactly these.
            ->withCount(['scopedNotes as notes_count']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return QuoteColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return QuoteColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return QuoteColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return QuoteAdvancedFilterCatalog::advancedFilters();
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
     * Map a Quote to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Quote $row */
        return [
            'id' => $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'opportunity' => $this->summarize($row->opportunity),
            'quote_workflow_status' => $this->summarizeWorkflowStatus($row->quoteWorkflowStatus),
            'commercial' => $this->summarize($row->commercial),
            'reporter' => $this->summarize($row->reporter),
            'supervisor' => $this->userSummary($row->supervisor),
            'revenue_net' => $row->revenue_net,
            'cost_net' => $row->cost_net,
            'margin_net' => $row->margin_net,
            'created_at' => $row->created_at,
            'company' => $this->summarizeCompany($row->company),
            'company_site' => $this->summarize($row->companySite),
            'operational_site' => $this->operationalSiteColumn->summarize($row->operationalSite),
            'notes_count' => (int) ($row->notes_count ?? 0),
            // "Gestori Account" (spec 0087, D-1/T-10): the avatar stack —
            // ordered by pivot position (Quote::managers()), no `position`
            // in the cell itself (mirrors OpportunitiesTableDefinition's own
            // `managers` projection). Never null at the column level: an
            // empty array when the Offerta has no team yet.
            'managers' => $row->managers->map(fn (User $user): array => $this->userSummary($user))->all(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * `companies` has no `name` column — its display name is `denomination`
     * (spec 0010), projected under `name` so the grid's generic RelationCell
     * reads it like any other `{id, name}` ref.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeCompany(?Company $company): ?array
    {
        return $company === null ? null : ['id' => $company->id, 'name' => $company->denomination];
    }

    /**
     * The workflow status projected WITH its `color` token, so the grid
     * renders the colored status badge; the generic summarize() would drop
     * it.
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeWorkflowStatus(?QuoteWorkflowStatus $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * A person summary carrying the inline avatar (data URI) so the supervisor
     * column renders a real avatar, not just initials — mirrors
     * OpportunitiesTableDefinition::userSummary(). Null when unset.
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
     * Allowed action keys for a single row, via QuotePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        // spec 0070: `.docx` generation is a read, gated the same as `view`.
        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'generate_document';
        }

        /** @var Quote $row */
        if ($this->allowsNotes($actor, $row)) {
            $allowed[] = 'notes';
        }

        return $allowed;
    }

    /**
     * The SAME rule RequestManagementNotable::authorizeRead re-checks when the
     * note endpoints are called, applied here to the Offerta's PARENT
     * Opportunity: spec 0085 keeps the note attached to the Opportunity and
     * only scopes it with `quote_id`, so reading an Offerta's notes is
     * reading its Opportunity's thread — never a `quotes.*` permission.
     *
     * Spec 0087, D-10: the gate evaluates the OFFERTA's own GA2 Operatore
     * (`quote.operator_id`, a real column — no eager-load needed), not the
     * parent Opportunity's — the two can diverge since the Offerta acquired
     * its own team (D-1). Was `$opportunity->operatorManager()?->id` before
     * this migration; mirrors OpportunitiesTableDefinition::allowsNotes'
     * shape, not its source any more. This action is an affordance only, the
     * endpoint authorizes for real.
     */
    private function allowsNotes(User $actor, Quote $quote): bool
    {
        if (! $actor->can('request-management.view')) {
            return false;
        }

        return $actor->can('request-management.viewAll')
            || $quote->operator_id === $actor->id;
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter($query, self::OPERATIONAL_SITE_RELATION, $this->filterValues($filter));

            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * `quote_workflow_status`'s advanced filter (QuoteAdvancedFilterCatalog)
     * is a SET filter matched by the related row's `name` (see the
     * constant's docblock) — the generic default (a plain `whereHas`-by-id
     * for `type: relation`/`async_search`) cannot express it.
     *
     * @param  Builder<Quote>  $query
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
                $this->relationColumns->applyNameWhereHas($query, 'quoteWorkflowStatus', $values);
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
     * @param  Builder<Quote>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, self::QUOTES_TABLE, self::OPERATIONAL_SITE_FK, $direction);

            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005).
     *
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($query, self::OPERATIONAL_SITE_FK, $search, $limit);
        }

        return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
    }

    /**
     * Delegate to QuoteService::delete() so the generic bulk-delete endpoint
     * cascades the quote's lines (AC-026) exactly like the single DELETE
     * /quotes/{quote} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var Quote $model */
        $this->service->delete($model);
    }
}
