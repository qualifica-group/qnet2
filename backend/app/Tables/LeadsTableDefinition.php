<?php

namespace App\Tables;

use App\Enums\LeadLifecycleStatus;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Tables\Leads\LeadAdvancedFilterCatalog;
use App\Tables\Leads\LeadAssignmentScope;
use App\Tables\Leads\LeadColumnCatalog;
use App\Tables\Leads\LeadOperationalSiteColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `leads` domain (spec 0024, spec 0041 D-1).
 *
 * `created_at` is the only real column handled entirely by the generic
 * engine. `registry`/`campaign`/`source`/`operator` are simple relation-name
 * derived columns (own FK on the lead), resolved generically via
 * DERIVED_RELATIONS — a `whereHas` set filter (allow-listed columns only,
 * never orderByRaw/whereRaw on raw input — backend.md §8) and a correlated
 * subquery sort, mirroring CampaignsTableDefinition. `operational_site` is
 * the ONE specially-derived column (BR-3: no own name, sort/filter/distinct
 * pass through the site's primary address `line1`) — delegated to
 * LeadOperationalSiteColumn (file-size split).
 */
class LeadsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    private const string OPERATIONAL_SITE_COLUMN = 'operational_site';

    private const string OPERATOR_FK = 'operator_id';

    private const string LEAD_STATUS_COLUMN = 'lead_status';

    private const string OPPORTUNITY_RELATION = 'opportunity';

    private const string OPPORTUNITY_EXISTS_ALIAS = 'opportunity_exists';

    private const string OPPORTUNITIES_TABLE = 'opportunities';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'registry' => ['relation' => 'registry', 'table' => 'registries', 'fk' => 'registry_id'],
        'campaign' => ['relation' => 'campaign', 'table' => 'campaigns', 'fk' => 'campaign_id'],
        'source' => ['relation' => 'source', 'table' => 'sources', 'fk' => 'source_id'],
        'operator' => ['relation' => 'operator', 'table' => 'users', 'fk' => 'operator_id'],
    ];

    public function __construct(
        private readonly LeadOperationalSiteColumn $operationalSiteColumn,
        private readonly LeadAssignmentScope $assignmentScope,
    ) {}

    public function domain(): string
    {
        return 'leads';
    }

    /**
     * @return class-string<Lead>
     */
    public function modelClass(): string
    {
        return Lead::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives LeadPolicy::viewAny from
    // modelClass() (leads.viewAny).

    /**
     * @return Builder<Lead>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow touches to avoid N+1 across the
        // page (operationalSite's address+city for the composed label, BR-3).
        return Lead::query()
            ->with(['registry', 'campaign', 'operationalSite.addresses.city', 'source', 'operator'])
            ->withExists(self::OPPORTUNITY_RELATION)
            // Operatore picker scope, resolved once per hydrated page (mapRow
            // sees one row at a time; LeadAssignmentScope is batch).
            ->afterQuery($this->assignmentScope->warm(...));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return LeadColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return LeadColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return LeadColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return LeadAdvancedFilterCatalog::advancedFilters();
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
     * Badge metadata for the derived `lead_status` lifecycle column.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== self::LEAD_STATUS_COLUMN) {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), LeadLifecycleStatus::options());
    }

    /**
     * The lifecycle badge label is localized by the frontend enum catalogue.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === self::LEAD_STATUS_COLUMN ? 'lead_lifecycle_status' : null;
    }

    /**
     * Map a Lead to the row payload. `notes` is emitted even though it is not
     * a declared (sortable/filterable) column, matching the data contract's
     * row shape. `actions` is attached by the generic TableService via
     * actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Lead $row */
        return [
            'id' => $row->id,
            'registry' => $this->summarize($row->registry),
            'campaign' => $this->summarize($row->campaign),
            'operational_site' => $this->operationalSiteColumn->summarize($row),
            'source' => $this->summarize($row->source),
            'operator' => $this->summarize($row->operator),
            'lead_status' => $row->lifecycleStatus()->value,
            'notes' => $row->notes,
            'created_at' => $row->created_at,
            // Non-visible keys read by the Operatore cell editor's
            // `relation.scope` (LeadColumnCatalog), like `notes` above.
            ...$this->assignmentScope->project($row),
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
     * Allowed action keys for a single row, via LeadPolicy — plus the
     * cross-resource `convert_to_opportunity` (spec 0044), gated on the
     * OpportunityPolicy::create ability rather than any LeadPolicy ability,
     * and hidden once the lead is already converted (`opportunity_exists`,
     * resolved by baseQuery's withExists — no per-row N+1).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var Lead $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        if (! $row->{self::OPPORTUNITY_EXISTS_ALIAS} && Gate::forUser($actor)->allows('create', Opportunity::class)) {
            $allowed[] = 'convert_to_opportunity';
        }

        return $allowed;
    }

    /**
     * `operational_site` (BR-3, spec 0032) has no relation-by-id equivalent —
     * the site carries no own name — so the generic default (which delegates
     * a `relation` type to a plain whereHas-by-id) cannot express it. Every
     * other advanced filter declared in LeadAdvancedFilterCatalog is a
     * standard relation-by-id, handled by the generic default.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::LEAD_STATUS_COLUMN) {
            $values = is_array($value) ? array_values($value) : [$value];
            $this->applyLeadStatusFilter($query, $values);

            return true;
        }

        if ($name === self::OPERATIONAL_SITE_COLUMN) {
            if (is_string($value) && $value !== '') {
                $this->operationalSiteColumn->applyAdvancedFilter($query, $value);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Handle the `registry`/`campaign`/`source`/`operator` set filters via
     * whereHas on the related row's name; `operational_site` is delegated to
     * LeadOperationalSiteColumn (BR-3). Every real column (created_at) falls
     * through to the generic engine.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $values = $this->filterValues($filter);

        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applyFilter($query, $values);

            return true;
        }

        if ($columnId === self::LEAD_STATUS_COLUMN) {
            $this->applyLeadStatusFilter($query, $values);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        if ($values !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($values): void {
                $relatedQuery->whereIn('name', $values);
            });
        }

        return true;
    }

    /**
     * `lead_status` set filter: converted leads have a generated opportunity,
     * associated leads have an operator and no opportunity, not-associated
     * leads have neither.
     *
     * @param  Builder<Lead>  $query
     * @param  array<int, string>  $values
     */
    private function applyLeadStatusFilter(Builder $query, array $values): void
    {
        $values = array_values(array_unique(array_intersect($values, $this->leadStatusValues())));

        if ($values === [] || count($values) === count($this->leadStatusValues())) {
            return;
        }

        $query->where(function (Builder $statusQuery) use ($values): void {
            foreach ($values as $value) {
                match ($value) {
                    LeadLifecycleStatus::ConvertedToOpportunity->value => $statusQuery->orWhereHas(self::OPPORTUNITY_RELATION),
                    LeadLifecycleStatus::Associated->value => $statusQuery->orWhere(function (Builder $associatedQuery): void {
                        $associatedQuery
                            ->whereNotNull(self::OPERATOR_FK)
                            ->whereDoesntHave(self::OPPORTUNITY_RELATION);
                    }),
                    LeadLifecycleStatus::NotAssociated->value => $statusQuery->orWhere(function (Builder $unassociatedQuery): void {
                        $unassociatedQuery
                            ->whereNull(self::OPERATOR_FK)
                            ->whereDoesntHave(self::OPPORTUNITY_RELATION);
                    }),
                    default => null,
                };
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function leadStatusValues(): array
    {
        return array_map(
            static fn (LeadLifecycleStatus $status): string => $status->value,
            LeadLifecycleStatus::cases(),
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * ORDER BY the related row's name via a correlated subquery for the 4
     * standard relational columns; `operational_site` (BR-3) is delegated to
     * LeadOperationalSiteColumn.
     *
     * @param  Builder<Lead>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            $this->operationalSiteColumn->applySort($query, $direction);

            return true;
        }

        if ($columnId === self::LEAD_STATUS_COLUMN) {
            $query->orderBy($this->leadStatusSortSubquery(), $direction);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "leads.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    private function leadStatusSortSubquery(): QueryBuilder
    {
        return DB::table(self::OPPORTUNITIES_TABLE)
            ->selectRaw('CASE WHEN COUNT(*) > 0 THEN 2 WHEN leads.operator_id IS NOT NULL THEN 1 ELSE 0 END')
            ->whereColumn(self::OPPORTUNITIES_TABLE.'.lead_id', 'leads.id');
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `registry`/`campaign`/
     * `source`/`operator` are plain related-row names; `operational_site`
     * (BR-3) is delegated to LeadOperationalSiteColumn.
     *
     * @param  Builder<Lead>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::OPERATIONAL_SITE_COLUMN) {
            return $this->operationalSiteColumn->distinctValues($search, $query, $limit);
        }

        if ($columnId === self::LEAD_STATUS_COLUMN) {
            return $this->filteredLeadStatusValues($search, $limit);
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return null;
        }

        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);

        return DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function filteredLeadStatusValues(?string $search, int $limit): array
    {
        $values = $this->leadStatusValues();

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $values = array_values(array_filter(
                $values,
                static fn (string $value): bool => str_contains(mb_strtolower($value), $needle),
            ));
        }

        return array_slice($values, 0, $limit);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
