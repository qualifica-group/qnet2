<?php

namespace App\Tables\OperationalSites;

use App\Models\Address;
use App\Models\City;
use App\Models\OperationalSite;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The 5 address-derived columns on the `operational-sites` table — city/
 * province/region (geo names) and street/postal_code (address text fields) —
 * each resolved from the site's PRIMARY address (OperationalSite -> Address,
 * directly, mirroring CompanyAddressColumns; no `country` column in this
 * grid, spec 0011 contract).
 *
 * Extracted out of OperationalSitesTableDefinition (file-size split,
 * engineering.md §6): option resolution, the derived set/text filters, the
 * derived sort, the Excel-like distinct-values resolution AND the derived
 * quick-search (spec 0009/0011, `applySearch`) for these 5 columns live in
 * one focused file.
 */
class OperationalSiteGeoColumns
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in a geo set filter. Caps the WHERE
     * IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Geo column id -> [belongsTo relation on Address, model class]. `region`
     * maps to the State model (a State is a region/regione in this hierarchy).
     *
     * @var array<string, array{relation: string, model: class-string}>
     */
    private const array GEO_COLUMNS = [
        'region' => ['relation' => 'state', 'model' => State::class],
        'province' => ['relation' => 'province', 'model' => Province::class],
        'city' => ['relation' => 'city', 'model' => City::class],
    ];

    /**
     * Free-text column id -> the Address column it reads.
     *
     * @var array<string, string>
     */
    private const array TEXT_COLUMNS = [
        'street' => 'line1',
        'postal_code' => 'postal_code',
    ];

    public function isGeoColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::GEO_COLUMNS);
    }

    public function isTextColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::TEXT_COLUMNS);
    }

    /**
     * Distinct geo NAMES actually present among sites' primary addresses.
     *
     * @return array<int, string>
     */
    public function options(string $columnId): array
    {
        [$relation, $model] = [self::GEO_COLUMNS[$columnId]['relation'], self::GEO_COLUMNS[$columnId]['model']];
        $foreignKey = "{$relation}_id";

        return $model::query()
            ->whereIn('id', function ($query) use ($foreignKey): void {
                $query->select($foreignKey)
                    ->from('addresses')
                    ->where('is_primary', true)
                    ->whereNotNull($foreignKey)
                    // Morph alias (enforced morphMap), not the FQCN.
                    ->where('addressable_type', (new OperationalSite)->getMorphClass());
            })
            ->pluck('name')
            ->map(GeoNameLocalizer::toItalian(...))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Excel-like distinct values (spec 0004): the option catalogue, optionally
     * narrowed by a case-insensitive substring search and capped to `$limit`.
     *
     * @return array<int, string|null>
     */
    public function distinctValues(string $columnId, ?string $search, int $limit): array
    {
        $options = $this->options($columnId);

        if ($search !== null && $search !== '') {
            return array_slice(array_values(array_filter(
                $options,
                static fn (string $option): bool => stripos($option, $search) !== false,
            )), 0, $limit);
        }

        // `null` is AG Grid's blank entry ("(Vuoti)"): the rows with no primary
        // address carrying that geo level. Always offered, like the option list
        // itself, which is the whole catalogue rather than the current rows'.
        return array_merge([null], array_slice($options, 0, $limit));
    }

    /**
     * Derived geo set filter matched by NAME on the related geo table, scoped
     * to the site's PRIMARY address. Bound parameters.
     *
     * @param  Builder<OperationalSite>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyGeoFilter(Builder $query, string $columnId, array $filter): void
    {
        $names = $this->setFilterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return;
        }

        // The option list is Italian; match on the DB name (English or already-Italian).
        $matchNames = GeoNameLocalizer::filterMatchNames($names);

        $relation = self::GEO_COLUMNS[$columnId]['relation'];

        $query->where(static function (Builder $group) use ($relation, $matchNames, $matchesBlank): void {
            if ($matchNames !== []) {
                $group->whereHas('addresses', static function (Builder $addressQuery) use ($relation, $matchNames): void {
                    $addressQuery->where('is_primary', true)
                        ->whereHas($relation, static function (Builder $geoQuery) use ($matchNames): void {
                            $geoQuery->whereIn('name', $matchNames);
                        });
                });
            }

            // The blank entry ("(Vuoti)"): no primary address reaching that geo
            // level at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('addresses', static function (Builder $addressQuery) use ($relation): void {
                    $addressQuery->where('is_primary', true)->has($relation);
                });
            }
        });
    }

    /**
     * Derived `street`/`postal_code` text filter: bound LIKE on the primary
     * address' matching column. Wildcards in user input are escaped.
     *
     * @param  Builder<OperationalSite>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyTextFilter(Builder $query, string $columnId, array $filter): void
    {
        $needle = $this->likeNeedle($filter);

        if ($needle === null) {
            return;
        }

        $column = self::TEXT_COLUMNS[$columnId];

        $query->whereHas('addresses', function (Builder $addressQuery) use ($column, $needle): void {
            $addressQuery->where('is_primary', true)->where($column, 'like', $needle);
        });
    }

    /**
     * Derived quick-search (spec 0009/0011): `city` matches the primary
     * address' City name, `street` matches its line1 — both bound LIKE,
     * combined into the caller's OR-group via `orWhereHas`. Any other column
     * id is not owned here (returns false; the definition's
     * `applyDerivedSearch` falls through).
     *
     * @param  Builder<OperationalSite>  $query
     */
    public function applySearch(Builder $query, string $columnId, string $pattern): bool
    {
        return match ($columnId) {
            'city' => $this->applySearchGeo($query, 'city', $pattern),
            'street' => $this->applySearchText($query, 'line1', $pattern),
            default => false,
        };
    }

    /**
     * Subquery selecting the primary address' related geo NAME for a site,
     * used as the ORDER BY key for that geo column.
     *
     * @return Builder<Model>
     */
    public function sortSubquery(string $columnId): Builder
    {
        [$relation, $model] = [self::GEO_COLUMNS[$columnId]['relation'], self::GEO_COLUMNS[$columnId]['model']];
        $geoTable = (new $model)->getTable();
        $foreignKey = "{$relation}_id";

        return $model::query()
            ->select("{$geoTable}.name")
            ->join('addresses', "addresses.{$foreignKey}", '=', "{$geoTable}.id")
            ->whereColumn('addresses.addressable_id', 'operational_sites.id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
    }

    /**
     * Subquery selecting the primary address' `street`/`postal_code` value.
     *
     * @return Builder<Model>
     */
    public function textSortSubquery(string $columnId): Builder
    {
        $column = self::TEXT_COLUMNS[$columnId];

        return Address::query()
            ->select($column)
            ->whereColumn('addresses.addressable_id', 'operational_sites.id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
    }

    /**
     * @param  Builder<OperationalSite>  $query
     */
    private function applySearchGeo(Builder $query, string $relation, string $pattern): bool
    {
        // The column shows Italian; a search typed in Italian must also reach
        // rows whose English DB name it localizes to (e.g. "napoli" -> Naples).
        $englishMatches = GeoNameLocalizer::englishNamesMatching($this->searchNeedle($pattern));

        $query->orWhereHas('addresses', function (Builder $addressQuery) use ($relation, $pattern, $englishMatches): void {
            $addressQuery->where('is_primary', true)
                ->whereHas($relation, function (Builder $geoQuery) use ($pattern, $englishMatches): void {
                    $geoQuery->where('name', 'like', $pattern)
                        ->when($englishMatches !== [], fn (Builder $inner) => $inner->orWhereIn('name', $englishMatches));
                });
        });

        return true;
    }

    /**
     * Recover the raw needle from the engine's escaped `%needle%` quick-search
     * pattern so it can be tested against the Italian display names — the
     * inverse of escapeLike() plus the `%...%` the generic engine wraps it in.
     */
    private function searchNeedle(string $pattern): string
    {
        $inner = trim($pattern, '%');

        return str_replace(['\\%', '\\_', '\\\\'], ['%', '_', '\\'], $inner);
    }

    /**
     * @param  Builder<OperationalSite>  $query
     */
    private function applySearchText(Builder $query, string $column, string $pattern): bool
    {
        $query->orWhereHas('addresses', function (Builder $addressQuery) use ($column, $pattern): void {
            $addressQuery->where('is_primary', true)->where($column, 'like', $pattern);
        });

        return true;
    }

    /**
     * Extract, sanitize and cap the string values of a set filter payload.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        return array_slice($clean, 0, self::MAX_FILTER_VALUES);
    }

    /**
     * Build a bound `%needle%` LIKE pattern from a text filter, or null when
     * the filter carries no usable value. Wildcards are escaped so they are
     * literal.
     *
     * @param  array<string, mixed>  $filter
     */
    private function likeNeedle(array $filter): ?string
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return '%'.$this->escapeLike((string) $value).'%';
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
