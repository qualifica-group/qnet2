<?php

declare(strict_types=1);

namespace App\Tables\AgentSalesReport;

use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use App\Services\Table\FilterApplier;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The client-derived columns of the `agent-sales-report` domain (spec 0365): `registry` (name of
 * the opportunity's Registry, set filter + sort + distinct values) and `city` (city of the
 * Registry card's primary address, text filter + sort). `quotes` carries neither, so each hook
 * reaches them through `opportunity.registry[.personalData.addresses.city]`.
 *
 * SECURITY: the filter payloads stay bound parameters (FilterApplier / whereIn), the sort uses a
 * correlated subquery over fixed table names, and LIKE wildcards are escaped (backend.md §8).
 */
final class AgentSalesReportRegistryColumns
{
    use HandlesBlankSetFilter;

    public const string REGISTRY = 'registry';

    public const string CITY = 'city';

    /** Caps the WHERE IN cardinality of the set filter (defence in depth). */
    private const int MAX_FILTER_VALUES = 200;

    private const string REGISTRY_RELATION = 'opportunity.registry';

    private const string ADDRESS_RELATION = 'opportunity.registry.personalData.addresses';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === self::REGISTRY) {
            $this->applyRegistryFilter($query, $filter);

            return true;
        }

        if ($columnId === self::CITY) {
            $query->whereHas(self::ADDRESS_RELATION, function (Builder $address) use ($columnConfig, $filter): void {
                $address->where('addresses.is_primary', true)->whereHas('city', function (Builder $city) use ($columnConfig, $filter): void {
                    $this->filterApplier->apply($city, 'cities.name', $columnConfig, $filter);
                });
            });

            return true;
        }

        return false;
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === self::REGISTRY) {
            $query->orderBy(
                DB::table('opportunities')
                    ->join('registries', 'registries.id', '=', 'opportunities.registry_id')
                    ->select('registries.name')
                    ->whereColumn('opportunities.id', 'quotes.opportunity_id')
                    ->limit(1),
                $direction,
            );

            return true;
        }

        if ($columnId === self::CITY) {
            $query->orderBy(
                DB::table('opportunities')
                    ->join('personal_data', function ($join): void {
                        $join->on('personal_data.personable_id', '=', 'opportunities.registry_id')
                            ->where('personal_data.personable_type', '=', (new Registry)->getMorphClass());
                    })
                    ->join('addresses', function ($join): void {
                        $join->on('addresses.addressable_id', '=', 'personal_data.id')
                            ->where('addresses.addressable_type', '=', (new PersonalData)->getMorphClass())
                            ->where('addresses.is_primary', '=', true);
                    })
                    ->join('cities', 'cities.id', '=', 'addresses.city_id')
                    ->select('cities.name')
                    ->whereColumn('opportunities.id', 'quotes.opportunity_id')
                    ->limit(1),
                $direction,
            );

            return true;
        }

        return false;
    }

    /**
     * Excel-like distinct values of `registry`, scoped by every other active filter.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>|null
     */
    public function distinctValues(string $columnId, ?string $search, Builder $query, int $limit): ?array
    {
        if ($columnId !== self::REGISTRY) {
            return null;
        }

        $registryIds = DB::table('opportunities')
            ->whereIn('id', (clone $query)->select('quotes.opportunity_id'))
            ->select('registry_id');

        $names = DB::table('registries')
            ->whereIn('id', $registryIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
            })
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($names, $search, fn (): bool => (clone $query)->whereDoesntHave(self::REGISTRY_RELATION)->exists());
    }

    /**
     * @param  Builder<Quote>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyRegistryFilter(Builder $query, array $filter): void
    {
        $values = $this->filterValues($filter);
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($values === [] && ! $matchesBlank) {
            return;
        }

        $query->where(function (Builder $group) use ($values, $matchesBlank): void {
            if ($values !== []) {
                $group->whereHas(self::REGISTRY_RELATION, static function (Builder $registry) use ($values): void {
                    $registry->whereIn('registries.name', $values);
                });
            }

            if ($matchesBlank) {
                $group->orWhereDoesntHave(self::REGISTRY_RELATION);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function filterValues(array $filter): array
    {
        $values = $filter['values'] ?? null;

        return is_array($values) ? array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES) : [];
    }
}
