<?php

namespace App\Tables\BusinessFunctions;

use App\Models\Address;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `business-functions` domain's `operational_sites` derived
 * column (spec 0010 REV): a belongsToMany with no real DB column of its own.
 * Mirrors LeadOperationalSiteColumn's identity convention (a site has no own
 * name — the primary address' `line1` IS the filter/sort/distinct-values
 * identity), adapted to a TO-MANY relation: `summarize()` returns one
 * {id, label} entry per associated site for row display (composed
 * "{line1} - {city}"), while filter/distinct-values match on the RAW `line1`
 * (never the composed label). NOT SORTABLE — a to-many value has no single
 * sort key (mirrors `users`). Extracted out of BusinessFunctionsTableDefinition
 * (file-size split, engineering.md §6).
 */
final class BusinessFunctionOperationalSitesColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The row's associated sites, composed-label display, relying on the
     * caller's baseQuery() eager-loading `operationalSites.addresses.city`.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function summarize(BusinessFunction $row): array
    {
        return $row->operationalSites->map(function (OperationalSite $site): array {
            /** @var Address|null $address */
            $address = $site->addresses->first();

            return ['id' => $site->id, 'label' => $this->composeLabel($address)];
        })->all();
    }

    /**
     * Derived set filter via whereHas on the sites' primary address `line1`.
     * Bound parameters, capped cardinality.
     *
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $values = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('operationalSites.addresses', static function (Builder $addressQuery) use ($values): void {
                    $addressQuery->where('is_primary', true)->whereIn('line1', $values);
                });
            }

            // The blank entry ("(Vuoti)"): no site, or no site with a primary
            // address line — the rows the value list cannot reach.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('operationalSites.addresses', static function (Builder $addressQuery): void {
                    $addressQuery->where('is_primary', true)->whereNotNull('line1');
                });
            }
        });

        return true;
    }

    /**
     * Excel-like distinct values (spec 0004/0005): distinct primary-address
     * `line1` values among the sites referenced by functions matching
     * `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<BusinessFunction>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $functionIds = (clone $query)->select('business_functions.id');

        $values = DB::table('addresses')
            ->join('business_function_operational_site', 'business_function_operational_site.operational_site_id', '=', 'addresses.addressable_id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->whereIn('business_function_operational_site.business_function_id', $functionIds)
            ->whereNotNull('addresses.line1')
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('addresses.line1', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('addresses.line1')
            ->limit($limit)
            ->pluck('addresses.line1')
            ->map(static fn (mixed $line1): string => (string) $line1)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('operationalSites.addresses', static function (Builder $addressQuery): void {
                $addressQuery->where('is_primary', true)->whereNotNull('line1');
            })
            ->exists());
    }

    private function composeLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
