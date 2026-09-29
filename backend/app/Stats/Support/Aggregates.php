<?php

declare(strict_types=1);

namespace App\Stats\Support;

use App\Stats\Widgets\DistributionItem;
use App\Stats\Widgets\TrendPoint;
use BackedEnum;
use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The aggregate query toolbox every StatsDefinition composes (spec 0026).
 *
 * Every KPI is a single SQL aggregate (COUNT/SUM/AVG + GROUP BY, LIMIT
 * server-side): no collection is ever materialized in memory and no "top N"
 * is sliced in PHP. Table/column names always come from definition constants,
 * never from request input, so the few raw fragments below (COUNT(*), the
 * driver's month expression) carry no injection surface.
 */
final class Aggregates
{
    private const string COUNT_ALIAS = 'aggregate';

    private const string BUCKET_ALIAS = 'bucket';

    private const string RELATED_ID_ALIAS = 'related_id';

    private const string TOP_ALIAS = 'top_related';

    /**
     * Top-N breakdown of `$query` (a query on the OWNING table) grouped by a
     * belongs-to relation, labelled with the related row's own column.
     *
     * @param  Builder  $query  base query on the owning table (or pivot table)
     * @param  string|Expression  $foreignKey  qualified FK (`leads.source_id`) or a
     *                                         static SQL expression built from
     *                                         definition constants (never input)
     * @param  string  $labelColumn  related label column, e.g. `name`
     * @param  string|null  $colorColumn  related color column, when the domain has one
     * @return array<int, DistributionItem>
     */
    public static function topRelated(
        Builder $query,
        string|Expression $foreignKey,
        string $relatedTable,
        string $labelColumn,
        int $limit,
        ?string $colorColumn = null,
    ): array {
        $columns = ["{$relatedTable}.id", "{$relatedTable}.{$labelColumn}"];

        if ($colorColumn !== null) {
            $columns[] = "{$relatedTable}.{$colorColumn}";
        }

        // Rank on the bare foreign key first (an index-only GROUP BY), then
        // label just the top N: joining the related table BEFORE grouping made
        // the database group 1M rows by a varchar label (10s on 1M opportunities).
        $top = $query
            ->selectRaw($query->getGrammar()->wrap($foreignKey).' as '.self::RELATED_ID_ALIAS)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->whereNotNull($foreignKey)
            ->groupBy($foreignKey)
            ->orderByDesc(self::COUNT_ALIAS)
            ->limit($limit);

        $rows = $query->newQuery()
            ->fromSub($top, self::TOP_ALIAS)
            ->join($relatedTable, "{$relatedTable}.id", '=', self::TOP_ALIAS.'.'.self::RELATED_ID_ALIAS)
            ->select([...$columns, self::TOP_ALIAS.'.'.self::COUNT_ALIAS])
            ->orderByDesc(self::TOP_ALIAS.'.'.self::COUNT_ALIAS)
            ->get();

        return $rows
            ->map(static fn (object $row): DistributionItem => new DistributionItem(
                key: (string) $row->id,
                label: (string) $row->{$labelColumn},
                value: (int) $row->{self::COUNT_ALIAS},
                color: $colorColumn === null ? null : self::nullableString($row->{$colorColumn}),
            ))
            ->all();
    }

    /**
     * Breakdown by a plain (enum-backed) column of `$table`. NULL rows are
     * excluded from the items — they are still part of the widget's `total`
     * denominator, which the caller passes in. Item labels/colors come from
     * the enum's presentation metadata (App\Enums\Concerns\HasMeta), falling
     * back to the raw stored value. The optional `$constrain` narrows the
     * query to a caller-owned scope (e.g. an actor's own rows for one
     * `resource` value) — definition constants only, never request input.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<int, DistributionItem>
     */
    public static function byEnumColumn(string $table, string $column, string $enum, ?Closure $constrain = null): array
    {
        $query = DB::table($table)->whereNotNull($column);

        if ($constrain !== null) {
            $constrain($query);
        }

        $rows = $query
            ->select($column)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->groupBy($column)
            ->orderByDesc(self::COUNT_ALIAS)
            ->get();

        return $rows
            ->map(static function (object $row) use ($column, $enum): DistributionItem {
                $value = (string) $row->{$column};
                $case = $enum::tryFrom($value);

                return new DistributionItem(
                    key: $value,
                    label: $case === null ? $value : $case->label(),
                    value: (int) $row->{self::COUNT_ALIAS},
                    color: $case?->color(),
                );
            })
            ->all();
    }

    /**
     * How many rows of `$table` own at least one row of `$relatedTable` (an
     * EXISTS semi-join: a single aggregate, never a loaded collection). The
     * optional `$constrain` narrows the inner query — e.g. the morph type of a
     * polymorphic child. Table/column names come from definition constants.
     */
    public static function countWithRelated(
        string $table,
        string $relatedTable,
        string $foreignKey,
        ?Closure $constrain = null,
    ): int {
        return DB::table($table)
            ->whereExists(static function (Builder $query) use ($table, $relatedTable, $foreignKey, $constrain): void {
                $query->from($relatedTable)
                    ->whereColumn("{$relatedTable}.{$foreignKey}", "{$table}.id");

                if ($constrain !== null) {
                    $constrain($query);
                }
            })
            ->count();
    }

    /**
     * How many DISTINCT non-null values `$column` holds — the count of the
     * parents actually referenced by `$table` (COUNT(DISTINCT ...) in SQL).
     */
    public static function distinctCount(string $table, string $column): int
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->distinct()
            ->count($column);
    }

    /**
     * Dense month-by-month row count over the last `$months` buckets (empty
     * months included with 0), keyed `YYYY-MM`. The optional `$constrain`
     * narrows the query to a caller-owned scope (e.g. an actor's own rows for
     * one `resource` value) — definition constants only, never request input.
     *
     * @return array<int, TrendPoint>
     */
    public static function monthlyTrend(string $table, string $column, int $months, ?Closure $constrain = null): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $expression = self::monthExpression("{$table}.{$column}");

        $query = DB::table($table)->where("{$table}.{$column}", '>=', $start->toDateString());

        if ($constrain !== null) {
            $constrain($query);
        }

        $counts = $query
            ->selectRaw("{$expression} as ".self::BUCKET_ALIAS)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->groupBy(DB::raw($expression))
            ->pluck(self::COUNT_ALIAS, self::BUCKET_ALIAS);

        $points = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $bucket = $start->copy()->addMonths($offset)->format('Y-m');

            $points[] = new TrendPoint($bucket, (int) ($counts[$bucket] ?? 0));
        }

        return $points;
    }

    /**
     * The driver-specific `YYYY-MM` bucket expression. `$column` is always a
     * definition constant, never request input.
     */
    private static function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
