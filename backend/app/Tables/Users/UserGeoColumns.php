<?php

namespace App\Tables\Users;

use App\Models\City;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Support\Geo\GeoNameLocalizer;
use App\Tables\Users\Concerns\CorrelatesPersonalDataToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The 4 geo-derived columns on the `users` table — country/region/province/
 * city — each resolving to the NAME of a related geo row on the user's
 * PRIMARY address (Address -> Country/State/Province/City).
 *
 * Extracted out of UsersTableDefinition (file-size split, engineering.md §6):
 * option resolution, the derived set filter, the derived sort and the
 * Excel-like distinct-values resolution for these 4 columns live in one
 * focused file. The geo NAME is used as BOTH the option token and the match
 * column, so there is never an id/label mismatch (unchanged behavior).
 */
class UserGeoColumns
{
    use CorrelatesPersonalDataToUser;

    /**
     * Maximum number of values honoured in a geo set filter. Caps the WHERE
     * IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Geo column id -> [belongsTo relation on Address, model class]. Note
     * `region` maps to the State model (a State is a region/regione in this
     * geo hierarchy).
     *
     * @var array<string, array{relation: string, model: class-string}>
     */
    private const array COLUMNS = [
        'country' => ['relation' => 'country', 'model' => Country::class],
        'region' => ['relation' => 'state', 'model' => State::class],
        'province' => ['relation' => 'province', 'model' => Province::class],
        'city' => ['relation' => 'city', 'model' => City::class],
    ];

    public function isGeoColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::COLUMNS);
    }

    /**
     * Distinct geo NAMES actually present among users' primary addresses,
     * localized to Italian and sorted. Resolving from values-in-use (rather
     * than the full geo tables, which can be huge — e.g. every city) keeps the
     * set-filter option list small and relevant. The list is shown in Italian;
     * applyFilter() reverses a selected value back to the English DB name.
     * Runs once per GET /columns, not per row.
     *
     * @return array<int, string>
     */
    public function options(string $columnId): array
    {
        [$relation, $model] = [self::COLUMNS[$columnId]['relation'], self::COLUMNS[$columnId]['model']];
        $foreignKey = "{$relation}_id";

        return $model::query()
            ->whereIn('id', function ($query) use ($foreignKey): void {
                $query->select($foreignKey)
                    ->from('addresses')
                    ->where('is_primary', true)
                    ->whereNotNull($foreignKey)
                    // Morph alias (enforced morphMap), not the FQCN.
                    ->where('addressable_type', (new PersonalData)->getMorphClass());
            })
            ->pluck('name')
            ->map(GeoNameLocalizer::toItalian(...))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Excel-like distinct values (spec 0004): the same option catalogue,
     * optionally narrowed by a case-insensitive substring search and capped
     * to `$limit`.
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

        // `null` is AG Grid's blank entry ("(Vuoti)"): the rows with no
        // primary address carrying that geo level. Always offered, like the
        // option list itself, which is the whole catalogue rather than the
        // values of the current rows.
        return array_merge([null], array_slice($options, 0, $limit));
    }

    /**
     * Derived geo set filter matched by NAME on the related geo table, scoped
     * to the user's PRIMARY address. Bound parameters.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = in_array(null, $values, true);

        if ($names === [] && ! $matchesBlank) {
            return;
        }

        // The option list is Italian; match on the DB name (English or already-Italian).
        $matchNames = GeoNameLocalizer::filterMatchNames($names);

        $relation = self::COLUMNS[$columnId]['relation'];

        $query->where(static function (Builder $group) use ($relation, $matchNames, $matchesBlank): void {
            if ($matchNames !== []) {
                $group->whereHas('personalData.addresses', static function (Builder $addressQuery) use ($relation, $matchNames): void {
                    $addressQuery->where('is_primary', true)
                        ->whereHas($relation, static function (Builder $geoQuery) use ($matchNames): void {
                            $geoQuery->whereIn('name', $matchNames);
                        });
                });
            }

            // The blank entry ("(Vuoti)"): no primary address reaching that
            // geo level at all — no card, no address, or an address with no
            // country/region/province/city set.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('personalData.addresses', static function (Builder $addressQuery) use ($relation): void {
                    $addressQuery->where('is_primary', true)->has($relation);
                });
            }
        });
    }

    /**
     * Subquery selecting the primary address' related geo NAME for a user,
     * used as the ORDER BY key for that geo column.
     *
     * @return Builder<Model>
     */
    public function sortSubquery(string $columnId): Builder
    {
        [$relation, $model] = [self::COLUMNS[$columnId]['relation'], self::COLUMNS[$columnId]['model']];
        $geoTable = (new $model)->getTable();
        $foreignKey = "{$relation}_id";

        return $this->correlateToUser(
            $model::query()
                ->select("{$geoTable}.name")
                ->join('addresses', "addresses.{$foreignKey}", '=', "{$geoTable}.id")
                ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
                ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
                ->where('addresses.is_primary', true),
        );
    }
}
