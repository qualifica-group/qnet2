<?php

namespace App\Tables\Stock;

use App\Models\OperationalSite;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The operational site columns of the stock grids (spec 0307): the site is
 * shown by its display label (alias or address), which is not a column, so the
 * set filter and the distinct values go through the id -> label map of the
 * sites. The sites are a short list, read in one query.
 */
final class StockSiteColumn
{
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Set filter on `$siteColumn` by the labels the user picked.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applySet(Builder $query, string $siteColumn, array $filter): void
    {
        $values = is_array($filter['values'] ?? null) ? array_slice(array_filter($filter['values'], 'is_string'), 0, self::MAX_FILTER_VALUES) : [];

        if ($values === []) {
            return;
        }

        $ids = array_keys(array_filter(self::labels(), static fn (string $label): bool => in_array($label, $values, true)));

        $query->whereIn($siteColumn, $ids);
    }

    /**
     * Labels of the sites that appear in `$siteColumn` of the rows of `$query`.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public static function distinct(Builder $query, string $siteColumn, ?string $search, int $limit): array
    {
        $usedIds = (clone $query)->reorder()->setEagerLoads([])->distinct()->pluck($siteColumn)->all();
        $labels = array_intersect_key(self::labels(), array_flip($usedIds));

        if ($search !== null && $search !== '') {
            $labels = array_filter($labels, static fn (string $label): bool => mb_stripos($label, $search) !== false);
        }

        $labels = array_unique($labels);
        sort($labels);

        return array_slice($labels, 0, $limit);
    }

    /**
     * @return array<int, string> site id => display label
     */
    private static function labels(): array
    {
        return OperationalSite::query()->with('addresses.city')->get()
            ->mapWithKeys(static fn (OperationalSite $site): array => [$site->id => OperationalSiteLabel::displayName($site)])
            ->all();
    }
}
