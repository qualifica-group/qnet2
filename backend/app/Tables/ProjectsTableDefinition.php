<?php

namespace App\Tables;

use App\Enums\GeoScopeLevel;
use App\Models\OperationalSite;
use App\Models\PipelineStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Projects\ProjectAdvancedFilterCatalog;
use App\Tables\Projects\ProjectColumnCatalog;
use App\Tables\Projects\ProjectRelationColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `projects` domain (spec 0023).
 *
 * Real columns (code, name, start_date, end_date, total_budget, target_lead,
 * created_at) are handled entirely by the generic engine. The 6
 * classification/geo FKs (pipeline_status, country, state, province, city,
 * partner) have no real column of their own — each is DERIVED against the
 * related row's `name`, resolved here generically via DERIVED_RELATIONS: a
 * `whereHas` set filter (allow-listed columns only, never orderByRaw/
 * whereRaw on raw input — backend.md §8), a correlated subquery sort
 * (pipeline_status only, per the column catalogue) and a
 * `SELECT DISTINCT` for the Excel-like filter list, mirroring
 * ReferentsTableDefinition's `referent_type` / ProductsTableDefinition's
 * `category`. `geo_scope` (spec 0027, D-2) is NOT in DERIVED_RELATIONS: it
 * has no FK/relation of its own (computed from the 4 geo ids), so it is
 * mapped directly in mapRow() and stays outside every filter/sort hook.
 *
 * Spec 0094, D-1/D-2: `business_function`/`product_category` are no longer
 * simple to-one FKs — they are AGGREGATED (to-many, via `productLines`)
 * columns, delegated to ProjectRelationColumns (file-size split,
 * engineering.md §6), mirroring OpportunityRelationColumns.
 */
class ProjectsTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of names honoured in a derived-column set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Allow-list of the 6 classification/geo FKs with no real column of
     * their own: relation accessor, related table and owning FK column,
     * keyed by the derived column id. Single source of truth for
     * applyDerivedFilter/applyDerivedSort/distinctValues below.
     * `business_function`/`product_category` (spec 0094) are NOT here: they
     * are AGGREGATED to-many columns, delegated to ProjectRelationColumns.
     *
     * @var array<string, array{relation: string, table: string, fk: string}>
     */
    private const array DERIVED_RELATIONS = [
        'pipeline_status' => ['relation' => 'pipelineStatus', 'table' => 'pipeline_statuses', 'fk' => 'pipeline_status_id'],
        'country' => ['relation' => 'country', 'table' => 'countries', 'fk' => 'country_id'],
        'state' => ['relation' => 'state', 'table' => 'states', 'fk' => 'state_id'],
        'province' => ['relation' => 'province', 'table' => 'provinces', 'fk' => 'province_id'],
        'city' => ['relation' => 'city', 'table' => 'cities', 'fk' => 'city_id'],
        'partner' => ['relation' => 'partner', 'table' => 'referents', 'fk' => 'partner_id'],
    ];

    public function __construct(private readonly ProjectRelationColumns $relationColumns) {}

    /**
     * The subset of DERIVED_RELATIONS whose `name` is geo reference data and
     * must render / filter in Italian (display localized, set-filter value
     * reversed back to the English DB name). The other derived relations carry
     * user data and are never localized (a company could be named "Milan").
     *
     * @var array<int, string>
     */
    private const array GEO_COLUMN_IDS = ['country', 'state', 'province', 'city'];

    public function domain(): string
    {
        return 'projects';
    }

    /**
     * @return class-string<Project>
     */
    public function modelClass(): string
    {
        return Project::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProjectPolicy::viewAny from
    // modelClass() (projects.viewAny).

    /**
     * @return Builder<Project>
     */
    public function baseQuery(): Builder
    {
        // Eager-load every classification FK to avoid N+1 when each row
        // projects all of them, plus `productLines.businessFunction`/
        // `productLines.productCategory` (spec 0094, AGGREGATED columns) and
        // the display-only `operational_site` column's composed-label source
        // (mirrors LeadOperationalSiteColumn).
        return Project::query()
            ->with(array_column(self::DERIVED_RELATIONS, 'relation'))
            ->with(['productLines.businessFunction', 'productLines.productCategory'])
            ->with('operationalSite.addresses.city');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProjectColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProjectColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProjectColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return ProjectAdvancedFilterCatalog::advancedFilters();
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
     * Map a Project to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Project $row */
        $mapped = [
            'id' => $row->id,
            'code' => $row->code,
            'name' => $row->name,
        ];

        foreach (self::DERIVED_RELATIONS as $columnId => $config) {
            $mapped[$columnId] = $this->summarize($row->{$config['relation']}, in_array($columnId, self::GEO_COLUMN_IDS, true));
        }

        // `pipeline_status` carries its color token so the grid renders the same
        // colored status badge as leads (summarize() alone drops the color).
        $mapped['pipeline_status'] = $this->summarizePipelineStatus($row->pipelineStatus);

        // Spec 0094, AC-025: AGGREGATED (to-many) columns — distinct related
        // names, comma-joined.
        $mapped['product_category'] = $this->summarizeNames($row->productLines->pluck('productCategory'));
        $mapped['business_function'] = $this->summarizeNames($row->productLines->pluck('businessFunction'));

        $mapped['geo_scope'] = GeoScopeLevel::for($row->country_id, $row->state_id, $row->province_id, $row->city_id)?->value;
        $mapped['operational_site'] = $this->summarizeOperationalSite($row->operationalSite);

        $mapped['start_date'] = $row->start_date;
        $mapped['end_date'] = $row->end_date;
        $mapped['total_budget'] = $row->total_budget;
        $mapped['target_lead'] = $row->target_lead;
        $mapped['created_at'] = $row->created_at;

        return $mapped;
    }

    /**
     * A related row projected to {id, name}. `$geo` localizes the name to
     * Italian (country/state/province/city) — never applied to the other
     * derived relations, whose names are user data.
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
     * The display-only `operational_site` column: the site has no own name
     * column, so its label is composed ("{line1} - {city}"), the same
     * composition LeadResource/OperationalSiteForSelectResource use. Relies
     * on baseQuery() eager-loading `operationalSite.addresses.city`.
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
     * The pipeline status projected WITH its `color` token, so the grid renders
     * the colored status badge; generic summarize() would drop the color.
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
     * Allowed action keys for a single row, via ProjectPolicy.
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

        if (Gate::forUser($actor)->allows('create', Project::class)) {
            $allowed[] = 'duplicate';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Handle the 6 derived set filters via whereHas on the related row's
     * name, generically resolved from DERIVED_RELATIONS; `business_function`/
     * `product_category` (spec 0094, AGGREGATED to-many) are delegated to
     * ProjectRelationColumns. Every real column falls through to the generic
     * engine.
     *
     * @param  Builder<Project>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return $this->relationColumns->applyFilter($query, $columnId, $filter);
        }

        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            // Geo columns list options in Italian; match on the DB name
            // (English as in world.sql, or already-Italian if seeded/imported so).
            if (in_array($columnId, self::GEO_COLUMN_IDS, true)) {
                $names = GeoNameLocalizer::filterMatchNames($names);
            }

            $query->whereHas($config['relation'], static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY a derived column's related-row name via a correlated
     * subquery, so sorting never needs a row-multiplying JOIN on the main
     * query. Only `pipeline_status` is declared sortable (spec
     * 0023 table_definitions); the other derived columns are never asked
     * to sort (not in sortableColumnIds()).
     *
     * @param  Builder<Project>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return false;
        }

        $subquery = DB::table($config['table'])
            ->select('name')
            ->whereColumn("{$config['table']}.id", "projects.{$config['fk']}")
            ->limit(1);

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for each of the 6 derived
     * columns: distinct related-row NAMES among the projects matching
     * `$query` (already scoped by every OTHER active filter).
     * `business_function`/`product_category` (spec 0094, AGGREGATED to-many)
     * are delegated to ProjectRelationColumns (AC-026: a join through
     * `project_product_lines`, no whereRaw on user input).
     *
     * @param  Builder<Project>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        $config = self::DERIVED_RELATIONS[$columnId] ?? null;

        if ($config === null) {
            return $this->relationColumns->distinctValues($columnId, $search, $query, $limit);
        }

        // Geo columns list options in Italian, so both the match against the
        // search term and the sort must run on the localized value in PHP
        // (the DB column is English). Values-in-use keep this list small.
        if (in_array($columnId, self::GEO_COLUMN_IDS, true)) {
            return $this->geoDistinctValues($config, $query, $search, $limit);
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
     * Excel-like distinct values for a GEO derived column, localized: the
     * distinct English names in use are translated to Italian, then filtered
     * by the (Italian) search term, sorted and capped — mirroring the geo
     * column catalogs of the other domains.
     *
     * @param  array{relation: string, table: string, fk: string}  $config
     * @param  Builder<Project>  $query
     * @return array<int, string>
     */
    private function geoDistinctValues(array $config, Builder $query, ?string $search, int $limit): array
    {
        $relatedIds = (clone $query)->whereNotNull($config['fk'])->select($config['fk']);

        $localized = DB::table($config['table'])
            ->whereIn('id', $relatedIds)
            ->distinct()
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) GeoNameLocalizer::toItalian((string) $name));

        if ($search !== null && $search !== '') {
            $localized = $localized->filter(static fn (string $name): bool => stripos($name, $search) !== false);
        }

        return $localized->sort()->values()->take($limit)->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
