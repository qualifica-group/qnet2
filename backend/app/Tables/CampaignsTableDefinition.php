<?php

namespace App\Tables;

use App\Enums\GeoScopeLevel;
use App\Models\Campaign;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Campaigns\CampaignAdvancedFilterCatalog;
use App\Tables\Campaigns\CampaignColumnCatalog;
use App\Tables\Campaigns\CampaignPipelineStatusResolver;
use App\Tables\Campaigns\CampaignRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `campaigns` domain (spec 0023).
 *
 * Real columns (code, name, start_date, end_date, total_budget, target_lead,
 * created_at) are handled entirely by the generic engine. `project` is a
 * simple relation-name derived column (own FK on
 * the campaign), resolved generically via DERIVED_RELATIONS — a `whereHas`
 * set filter (allow-listed columns only, never orderByRaw/whereRaw on raw
 * input — backend.md §8) and, for `project` only (the sole sortable one), a
 * correlated subquery sort, mirroring ProjectsTableDefinition.
 *
 * `pipeline_status` is the ONE doubly-derived column (BR-2/AC-032: a linked
 * campaign's OWN pipeline_status_id is NULL, its effective status is read
 * through the project) — delegated to CampaignPipelineStatusResolver (file-
 * size split) rather than a SQL-level JOIN/COALESCE on the base query (which
 * would risk ambiguous column names: `campaigns` and `projects` share
 * several). It is filterable-only (never sortable — spec 0023
 * table_definitions).
 *
 * `business_function`/`product_category` (spec 0094, AC-025/AC-027) are NEW
 * AGGREGATED (to-many) columns, own-or-through-project (BR-2, the SAME
 * pattern `pipeline_status` uses) — delegated to CampaignRelationColumns
 * (file-size split), mirroring OpportunityRelationColumns/
 * ProjectRelationColumns.
 */
class CampaignsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The doubly-derived `pipeline_status` column id (AC-032), delegated to
     * CampaignPipelineStatusResolver rather than the DERIVED_RELATIONS map.
     */
    private const string PROJECT_STATUS_COLUMN = 'pipeline_status';

    /**
     * Simple (single-hop) relation-name derived columns: relation accessor,
     * related table and owning FK column, keyed by the derived column id.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'project' => ['relation' => 'project', 'table' => 'projects', 'fk' => 'project_id'],
    ];

    public function __construct(
        private readonly CampaignPipelineStatusResolver $pipelineStatusResolver,
        private readonly CampaignRelationColumns $relationColumns,
    ) {}

    public function domain(): string
    {
        return 'campaigns';
    }

    /**
     * @return class-string<Campaign>
     */
    public function modelClass(): string
    {
        return Campaign::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives CampaignPolicy::viewAny
    // from modelClass() (campaigns.viewAny).

    /**
     * @return Builder<Campaign>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every relation mapRow/effectiveStatus touches to avoid
        // N+1 across the page. `project.{country,state,province,city}` plus
        // the campaign's own geo (spec 0027, BR-5) feed the merged display
        // columns below.
        return Campaign::query()->with([
            'project.pipelineStatus',
            'project.country',
            'project.state',
            'project.province',
            'project.city',
            'project.productLines.businessFunction',
            'project.productLines.productCategory',
            'pipelineStatus',
            'country',
            'state',
            'province',
            'city',
            'productLines.businessFunction',
            'productLines.productCategory',
            'operationalSite.addresses.city',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CampaignColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CampaignColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CampaignColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return CampaignAdvancedFilterCatalog::advancedFilters();
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
     * Map a Campaign to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Campaign $row */
        $project = $row->project;
        $country = $row->country ?? $project?->country;
        $state = $row->state ?? $project?->state;
        $province = $row->province ?? $project?->province;
        $city = $row->city ?? $project?->city;
        $productLines = $project?->productLines ?? $row->productLines;

        return [
            'id' => $row->id,
            'code' => $row->code,
            'project' => $this->summarizeProject($project),
            'name' => $row->name,
            'pipeline_status' => $this->summarizePipelineStatus($this->pipelineStatusResolver->effectiveStatus($row)),
            'country' => $this->summarize($country, geo: true),
            'state' => $this->summarize($state, geo: true),
            'province' => $this->summarize($province, geo: true),
            'city' => $this->summarize($city, geo: true),
            'geo_scope' => GeoScopeLevel::for($country?->id, $state?->id, $province?->id, $city?->id)?->value,
            // Spec 0094, AC-027: AGGREGATED (to-many) columns — the EFFECTIVE
            // rows (the linked project's when derived, else the campaign's
            // own), distinct related names comma-joined.
            'product_category' => $this->summarizeNames($productLines->pluck('productCategory')),
            'business_function' => $this->summarizeNames($productLines->pluck('businessFunction')),
            'operational_site' => $this->summarizeOperationalSite($row->operationalSite),
            'start_date' => $row->start_date,
            'end_date' => $row->end_date,
            'total_budget' => $row->total_budget,
            'target_lead' => $row->target_lead,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeProject(?Model $project): ?array
    {
        if ($project === null) {
            return null;
        }

        /** @var Project $project */
        return ['id' => $project->id, 'name' => sprintf('%s — %s', $project->code, $project->name)];
    }

    /**
     * A related row projected to {id, name}. `$geo` localizes the name to
     * Italian (country/state/province/city) — never applied to the other
     * relations, whose names are user data.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarize(?Model $related, bool $geo = false): ?array
    {
        if ($related === null) {
            return null;
        }

        $name = $geo ? GeoNameLocalizer::toItalian($related->name) : $related->name;

        return ['id' => $related->id, 'name' => $name];
    }

    /**
     * Display value for an AGGREGATED to-many column (spec 0094): the
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
     * The display-only `operational_site` column: the campaign's OWN site
     * (never the project's — the field is independently editable and stored,
     * spec: prefill-modifiable, not read-through). No own name column, so its
     * label is composed ("{line1} - {city}"), the same composition
     * LeadResource/OperationalSiteForSelectResource use. Relies on
     * baseQuery() eager-loading `operationalSite.addresses.city`.
     *
     * @return array{id: int, label: string}|null
     */
    private function summarizeOperationalSite(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        /** @var OperationalSite $related */
        $address = $related->addresses->first();
        $city = $address?->city?->localizedName();
        $label = $address === null ? '' : ($city === null ? (string) $address->line1 : "{$address->line1} - {$city}");

        return ['id' => $related->id, 'label' => $label];
    }

    /**
     * The effective pipeline status projected WITH its `color` token, so the grid
     * renders the colored status badge like leads (generic summarize() would drop
     * the color). Mirrors ProjectsTableDefinition::summarizePipelineStatus.
     *
     * @return array{id: int, name: string, color: ?string}|null
     */
    private function summarizePipelineStatus(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        /** @var PipelineStatus $related */
        return ['id' => $related->id, 'name' => $related->name, 'color' => $related->color];
    }

    /**
     * Allowed action keys for a single row, via CampaignPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
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

        if (Gate::forUser($actor)->allows('create', Campaign::class)) {
            $allowed[] = 'duplicate';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * `pipeline_status` (BR-2/AC-032) has no relation-by-id equivalent through
     * the generic default: a linked campaign's OWN status is NULL, so it must
     * match the campaign's own status OR its linked project's — delegated to
     * CampaignPipelineStatusResolver::applyIdFilter(). Every other advanced
     * filter declared in CampaignAdvancedFilterCatalog (`project`/
     * `partner` relation-by-id, `budget_range`/`created_range`
     * direct-column) is handled by the generic default.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::PROJECT_STATUS_COLUMN) {
            if (is_array($value)) {
                $this->pipelineStatusResolver->applyIdFilter($query, $value);
            } elseif (is_scalar($value) && $value !== '') {
                $this->pipelineStatusResolver->applyIdFilter($query, [$value]);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Handle the `project` set filter via whereHas on
     * the related row's name; `pipeline_status` is delegated to
     * CampaignPipelineStatusResolver (AC-032); `business_function`/
     * `product_category` (spec 0094, AGGREGATED to-many, AC-025/AC-027) are
     * delegated to CampaignRelationColumns. Every real column falls through
     * to the generic engine.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $names = $this->filterNames($filter);

        if ($columnId === self::PROJECT_STATUS_COLUMN) {
            $this->pipelineStatusResolver->applyFilter($query, $names);

            return true;
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return $this->relationColumns->applyFilter($query, $columnId, $filter);
        }

        if ($names !== []) {
            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterNames(array $filter): array
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
     * ORDER BY the linked project's name via a correlated subquery — only
     * `project` is declared sortable (spec 0023 table_definitions);
     * `pipeline_status` is never asked to sort (not in
     * sortableColumnIds()).
     *
     * @param  Builder<Campaign>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null || $columnId !== 'project') {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "campaigns.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005). `project` is a
     * plain related-row name; `pipeline_status` is delegated to
     * CampaignPipelineStatusResolver (AC-032); `business_function`/
     * `product_category` (spec 0094, AC-026/AC-027) are delegated to
     * CampaignRelationColumns.
     *
     * @param  Builder<Campaign>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId === self::PROJECT_STATUS_COLUMN) {
            return $this->pipelineStatusResolver->distinctValues($search, $query, $limit);
        }

        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
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
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
