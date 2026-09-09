<?php

namespace App\Tables\Users;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The 9 employment-derived grid columns on the `users` table (spec 0015):
 * company/reports_to (related NAMES), relationship_type/qualification_type
 * (enums), is_manager (boolean), hired_at/terminated_at (dates),
 * business_function (AGGREGATED over the competence rows since spec 0111
 * D-1, delegated to UserBusinessFunctionColumn) and operational_site
 * (formatted address line, CONDITIONS-ONLY — mirrors `primary_address`,
 * spec 0005 UX decision; cell/filter/sort split physical-vs-remote per spec
 * 0103 D-7, delegated to UserOperationalSiteColumn).
 *
 * None of these has a real column on `users`: every filter/sort/distinct-
 * values resolution goes through `employment` (a hasOne), matched via
 * whereHas + a correlated sort subquery — mirroring UserGeoColumns/
 * UserPersonalDataColumns. Set/date filters delegate to the shared
 * FilterApplier (bound parameters, same operator set as real columns) so no
 * filter logic is duplicated for the two date columns.
 *
 * Extracted out of UsersTableDefinition (file-size split, engineering.md §6).
 */
class UserEmploymentColumns
{
    /**
     * Maximum number of values honoured in a set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Column id -> [related table, name column, relation path for whereHas,
     * owning FK on `employment_profiles`].
     *
     * @var array<string, array{table: string, nameColumn: string, relation: string, fk: string}>
     */
    private const array RELATED_NAME_COLUMNS = [
        'company' => ['table' => 'companies', 'nameColumn' => 'denomination', 'relation' => 'employment.company', 'fk' => 'company_id'],
        'reports_to' => ['table' => 'users', 'nameColumn' => 'name', 'relation' => 'employment.reportsTo', 'fk' => 'reports_to_id'],
    ];

    private const array ENUM_COLUMNS = ['relationship_type', 'qualification_type'];

    private const array DATE_COLUMNS = ['hired_at', 'terminated_at'];

    public function __construct(
        private readonly FilterApplier $filterApplier,
        private readonly UserOperationalSiteColumn $operationalSiteColumn,
        private readonly UserBusinessFunctionColumn $businessFunctionColumn,
    ) {}

    public function isEmploymentColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::RELATED_NAME_COLUMNS)
            || in_array($columnId, self::ENUM_COLUMNS, true)
            || in_array($columnId, self::DATE_COLUMNS, true)
            || $columnId === 'is_manager'
            || $columnId === 'business_function'
            || $columnId === 'operational_site';
    }

    /**
     * Row fields derived from the eager-loaded employment profile.
     *
     * @return array<string, mixed>
     */
    public function mapRow(?EmploymentProfile $employment): array
    {
        return [
            'business_function' => $this->businessFunctionColumn->label($employment),
            'company' => $employment?->company?->denomination,
            'operational_site' => $this->operationalSiteColumn->label($employment),
            'relationship_type' => $employment?->relationship_type?->value,
            'qualification_type' => $employment?->qualification_type?->value,
            'is_manager' => $employment?->is_manager ?? false,
            'reports_to' => $this->userSummary($employment?->reportsTo),
            'hired_at' => $employment?->hired_at,
            'terminated_at' => $employment?->terminated_at,
        ];
    }

    /**
     * `reports_to` as an `{id, name}` summary (not a bare name), so the grid can
     * render it as a clickable user chip that opens the person's detail — the
     * same row shape every other user column emits.
     *
     * @return array{id: int, name: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return ['id' => $user->id, 'name' => $user->name];
    }

    /**
     * The snake_case enum key (config/config.php form_enums) for the two
     * enum columns, so the frontend localizes their options from its own
     * i18n `enums.*` namespace.
     */
    public function enumKeyFor(string $columnId): ?string
    {
        return in_array($columnId, self::ENUM_COLUMNS, true) ? $columnId : null;
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): void
    {
        if ($columnId === 'business_function') {
            $this->businessFunctionColumn->applyFilter($query, $filter);

            return;
        }

        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            $this->filterByRelatedName($query, $columnId, $filter);

            return;
        }

        if (in_array($columnId, self::ENUM_COLUMNS, true) || $columnId === 'is_manager') {
            $query->whereHas('employment', fn (Builder $q): mixed => $this->filterApplier->apply($q, $columnId, ['filterType' => 'set'], $filter));

            return;
        }

        if (in_array($columnId, self::DATE_COLUMNS, true)) {
            $query->whereHas('employment', fn (Builder $q): mixed => $this->filterApplier->apply($q, $columnId, ['filterType' => 'date'], $filter));

            return;
        }

        if ($columnId === 'operational_site') {
            $this->operationalSiteColumn->applyFilter($query, $filter);
        }
    }

    /**
     * Derived set filter via a nested whereHas on the related NAME column,
     * bound + capped cardinality (mirrors BusinessFunctionsTableDefinition's
     * `manager`/`users` filters).
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByRelatedName(Builder $query, string $columnId, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $column = self::RELATED_NAME_COLUMNS[$columnId];

        $query->whereHas($column['relation'], static function (Builder $relatedQuery) use ($column, $names): void {
            $relatedQuery->whereIn($column['nameColumn'], $names);
        });
    }

    /**
     * ORDER BY the employment-derived value via a correlated subquery scoped
     * to `employment_profiles.user_id`, so sorting never needs a row-
     * multiplying JOIN (employment is truly 1:1, but every related name is a
     * further hop away).
     *
     * @return Builder<Model>|null
     */
    public function sortSubquery(string $columnId): ?Builder
    {
        if ($columnId === 'business_function') {
            return $this->businessFunctionColumn->sortSubquery();
        }

        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            return $this->relatedNameSortSubquery($columnId);
        }

        if (in_array($columnId, self::ENUM_COLUMNS, true) || $columnId === 'is_manager' || in_array($columnId, self::DATE_COLUMNS, true)) {
            return EmploymentProfile::query()
                ->select($columnId)
                ->whereColumn('employment_profiles.user_id', 'users.id')
                ->limit(1);
        }

        if ($columnId === 'operational_site') {
            return $this->operationalSiteColumn->sortSubquery();
        }

        return null;
    }

    /**
     * @return Builder<Model>
     */
    private function relatedNameSortSubquery(string $columnId): Builder
    {
        $column = self::RELATED_NAME_COLUMNS[$columnId];
        // `reports_to` self-joins `users` (the outer query's own table), so it
        // needs an alias; the other two related tables never collide.
        $joinTarget = $columnId === 'reports_to' ? "{$column['table']} as employment_reports_to" : $column['table'];
        $joinAlias = $columnId === 'reports_to' ? 'employment_reports_to' : $column['table'];

        return EmploymentProfile::query()
            ->select("{$joinAlias}.{$column['nameColumn']}")
            ->join($joinTarget, "{$joinAlias}.id", '=', "employment_profiles.{$column['fk']}")
            ->whereColumn('employment_profiles.user_id', 'users.id')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 6 columns that have
     * one (business_function [aggregated]/company/reports_to/relationship_type/
     * qualification_type/is_manager). `operational_site`/`hired_at`/
     * `terminated_at` declare `hasFilterValues:false` (UserColumnCatalog), so
     * TableService never calls this method for them.
     *
     * @param  Builder<User>  $query
     * @return array<int, scalar>
     */
    public function distinctValues(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        if ($columnId === 'business_function') {
            return $this->businessFunctionColumn->distinctValues($query, $search, $limit);
        }

        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            return $this->distinctRelatedNames($query, $columnId, $search, $limit);
        }

        if ($columnId === 'relationship_type') {
            return $this->distinctEnumValues(RelationshipTypeEnum::values(), $search, $limit);
        }

        if ($columnId === 'qualification_type') {
            return $this->distinctEnumValues(QualificationTypeEnum::values(), $search, $limit);
        }

        if ($columnId === 'is_manager') {
            return [true, false];
        }

        return [];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function distinctEnumValues(array $values, ?string $search, int $limit): array
    {
        $matches = $search === null || $search === ''
            ? $values
            : array_values(array_filter($values, static fn (string $value): bool => stripos($value, $search) !== false));

        return array_slice($matches, 0, $limit);
    }

    /**
     * @param  Builder<User>  $query
     * @return array<int, string>
     */
    private function distinctRelatedNames(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        $column = self::RELATED_NAME_COLUMNS[$columnId];

        $userIds = (clone $query)->select('users.id');

        $relatedIds = DB::table('employment_profiles')
            ->select($column['fk'])
            ->whereIn('user_id', $userIds)
            ->whereNotNull($column['fk']);

        return DB::table($column['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search, $column): void {
                $builder->where($column['nameColumn'], 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($column['nameColumn'])
            ->limit($limit)
            ->pluck($column['nameColumn'])
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }
}
