<?php

namespace App\Tables\Leads;

use App\Models\Address;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves the `leads` domain's `operational_site` derived column (BR-3):
 * the site has no own name column, so its DISPLAY label is composed
 * ("{line1} - {city}") but sort/filter/distinct-values all pass through the
 * site's PRIMARY address `line1` (never the composed label), mirroring
 * OperationalSiteGeoColumns/OperationalSiteService's own sort subquery.
 * Extracted out of LeadsTableDefinition (file-size split, engineering.md §6).
 */
final class LeadOperationalSiteColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of values honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The row's composed display label ("{line1} - {city}", or just line1
     * when the address has no city, or null when the lead has no site) — the
     * same composition OperationalSiteForSelectResource/LeadResource use.
     * Relies on the caller's baseQuery() eager-loading
     * `operationalSite.addresses.city`.
     *
     * @return array{id: int, label: string}|null
     */
    public function summarize(Lead $row): ?array
    {
        $site = $row->operationalSite;

        if ($site === null) {
            return null;
        }

        /** @var Address|null $address */
        $address = $site->addresses->first();

        return ['id' => $site->id, 'label' => $this->composeLabel($address)];
    }

    /**
     * Match leads whose site's primary address `line1` is among $values
     * (bound parameters, no raw SQL).
     *
     * @param  Builder<Lead>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values, bool $matchesBlank = false): void
    {
        $values = array_slice($values, 0, self::MAX_FILTER_VALUES);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas('operationalSite.addresses', static function (Builder $addressQuery) use ($values): void {
                    $addressQuery->where('is_primary', true)->whereIn('line1', $values);
                });
            }

            // The blank entry ("(Vuoti)"): no site, or a site with no primary
            // address line — the rows the value list cannot reach.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('operationalSite.addresses', static function (Builder $addressQuery): void {
                    $addressQuery->where('is_primary', true)->whereNotNull('line1');
                });
            }
        });
    }

    /**
     * Advanced-filter (spec 0032) free-text search on the site's primary
     * address `line1` — a bound, escaped LIKE. Distinct from the exact-match
     * Set filter above (applyFilter()): the advanced panel's `text` widget
     * searches a substring, not a closed value list.
     *
     * @param  Builder<Lead>  $query
     */
    public function applyAdvancedFilter(Builder $query, string $needle): void
    {
        $pattern = '%'.$this->escapeLike($needle).'%';

        $query->whereHas('operationalSite.addresses', static function (Builder $addressQuery) use ($pattern): void {
            $addressQuery->where('is_primary', true)->where('line1', 'like', $pattern);
        });
    }

    /**
     * ORDER BY the site's primary address `line1` via a correlated subquery.
     *
     * @param  Builder<Lead>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->sortSubquery(), $direction);
    }

    /**
     * Distinct primary-address `line1` values among the sites referenced by
     * leads matching $query (already scoped by every other active filter),
     * optionally narrowed by a case-insensitive substring search.
     *
     * @param  Builder<Lead>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $siteIds = (clone $query)->whereNotNull('operational_site_id')->pluck('operational_site_id');

        $values = Address::query()
            ->whereIn('addressable_id', $siteIds)
            ->where('addressable_type', (new OperationalSite)->getMorphClass())
            ->where('is_primary', true)
            ->whereNotNull('line1')
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('line1', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('line1')
            ->limit($limit)
            ->pluck('line1')
            ->map(static fn (mixed $line1): string => (string) $line1)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('operationalSite.addresses', static function (Builder $addressQuery): void {
                $addressQuery->where('is_primary', true)->whereNotNull('line1');
            })
            ->exists());
    }

    /**
     * Subquery selecting the lead's site's primary address `line1`.
     *
     * @return Builder<Address>
     */
    private function sortSubquery(): Builder
    {
        return Address::query()
            ->select('line1')
            ->whereColumn('addresses.addressable_id', 'leads.operational_site_id')
            ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
            ->where('addresses.is_primary', true)
            ->limit(1);
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
