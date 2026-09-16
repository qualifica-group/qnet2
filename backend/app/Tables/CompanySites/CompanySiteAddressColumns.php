<?php

namespace App\Tables\CompanySites;

use App\Models\Address;
use App\Models\City;
use App\Models\CompanySite;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The 4 address-derived columns on the `company-sites` table — city/province/
 * region (geo names) and postal_code — each resolved from the CARD's PRIMARY
 * address (CompanySite -> personalData -> Address). The address lives on the
 * personal-data card (morph `addressable_type='personal_data'`), which in turn
 * belongs to the site (morph `personable_type='company_site'`), so every
 * derivation hops through `personal_data`. NO `country` column in this grid
 * (spec 0020 contract, unlike companies').
 *
 * Extracted out of CompanySitesTableDefinition (file-size split, engineering.md
 * §6): option resolution, the derived set/text filters, the derived sort and
 * the Excel-like distinct-values resolution for the 3 geo columns live in one
 * focused file.
 */
class CompanySiteAddressColumns
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

    public function isGeoColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::GEO_COLUMNS);
    }

    public function isPostalCodeColumn(string $columnId): bool
    {
        return $columnId === 'postal_code';
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
                    // The address belongs to the site's card (morph alias),
                    // and the card belongs to a company site.
                    ->where('addressable_type', (new PersonalData)->getMorphClass())
                    ->whereIn('addressable_id', function ($cardQuery): void {
                        $cardQuery->select('id')
                            ->from('personal_data')
                            ->where('personable_type', (new CompanySite)->getMorphClass());
                    });
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
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): void
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
                $group->whereHas('personalData.addresses', static function (Builder $addressQuery) use ($relation, $matchNames): void {
                    $addressQuery->where('is_primary', true)
                        ->whereHas($relation, static function (Builder $geoQuery) use ($matchNames): void {
                            $geoQuery->whereIn('name', $matchNames);
                        });
                });
            }

            // The blank entry ("(Vuoti)"): no primary address reaching that geo
            // level at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('personalData.addresses', static function (Builder $addressQuery) use ($relation): void {
                    $addressQuery->where('is_primary', true)->has($relation);
                });
            }
        });
    }

    /**
     * Derived `postal_code` text filter: bound LIKE on the primary address'
     * postal code. Wildcards in user input are escaped.
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyPostalCodeFilter(Builder $query, array $filter): void
    {
        $needle = $this->likeNeedle($filter);

        if ($needle === null) {
            return;
        }

        $query->whereHas('personalData.addresses', function (Builder $addressQuery) use ($needle): void {
            $addressQuery->where('is_primary', true)->where('postal_code', 'like', $needle);
        });
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
            ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
            ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
            ->whereColumn('personal_data.personable_id', 'company_sites.id')
            ->where('personal_data.personable_type', (new CompanySite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
    }

    /**
     * Subquery selecting the card's primary address' postal code for a site.
     *
     * @return Builder<Model>
     */
    public function postalCodeSortSubquery(): Builder
    {
        return Address::query()
            ->select('postal_code')
            ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
            ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
            ->whereColumn('personal_data.personable_id', 'company_sites.id')
            ->where('personal_data.personable_type', (new CompanySite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
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
