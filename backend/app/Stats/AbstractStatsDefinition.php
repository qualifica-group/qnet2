<?php

declare(strict_types=1);

namespace App\Stats;

use App\Models\User;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\DistributionItem;
use App\Stats\Widgets\DistributionWidget;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\StatSubtitle;
use App\Stats\Widgets\StatWidget;
use App\Stats\Widgets\TrendChart;
use App\Stats\Widgets\TrendPoint;
use App\Stats\Widgets\TrendWidget;
use App\Support\ConversionRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Shared base for every concrete StatsDefinition (spec 0026).
 *
 * Holds the two cross-cutting parts so a concrete definition only declares
 * its model and its KPIs:
 *  - fail-safe authorization: the model's Policy `viewAny` (mirrors
 *    AbstractTableDefinition — a definition cannot "forget" the check nor
 *    hardcode `true`; an unregistered Policy denies, never fails open);
 *  - the widget builders, which derive every i18n label key from the domain
 *    (`{domainCamel}.stats.{keyCamel}`) so the frontend key space is
 *    mechanical, never hand-typed per module.
 */
abstract class AbstractStatsDefinition implements StatsDefinition
{
    /** Rows kept in a "top N" distribution (LIMIT server-side, never a PHP slice). */
    protected const int TOP_LIMIT = 10;

    /** Months in a trend series (dense: empty months are emitted with 0). */
    protected const int TREND_MONTHS = 12;

    public function authorizeViewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('viewAny', $this->modelClass());
    }

    /**
     * Total rows of the domain's model — the usual denominator of the
     * percent stats and of the distributions.
     */
    protected function totalRows(): int
    {
        /** @var Model $model */
        $model = new ($this->modelClass());

        return $model->newQuery()->count();
    }

    protected function stat(
        string $key,
        int|float|null $value,
        StatFormat $format = StatFormat::Number,
        ?string $icon = null,
        ?StatSubtitle $subtitle = null,
    ): StatWidget {
        return new StatWidget(
            key: $key,
            label: $this->labelKey($key),
            value: $value,
            format: $format,
            subtitle: $subtitle,
            icon: $icon,
        );
    }

    /**
     * A percent KPI: `value` is the share of `$count` over `$total`, NULL on a
     * zero denominator (never "0%" — BR-1, App\Support\ConversionRate), with
     * the absolute count carried in the subtitle so the card shows both.
     */
    protected function percentStat(string $key, int $count, int $total, ?string $icon = null): StatWidget
    {
        return $this->stat(
            key: $key,
            value: ConversionRate::of($count, $total),
            format: StatFormat::Percent,
            icon: $icon,
            subtitle: new StatSubtitle($this->labelKey("{$key}_subtitle"), $count),
        );
    }

    /**
     * @param  array<int, DistributionItem>  $items
     */
    protected function distribution(
        string $key,
        array $items,
        int $total,
        DistributionChart $chart = DistributionChart::Bars,
    ): DistributionWidget {
        return new DistributionWidget(
            key: $key,
            label: $this->labelKey($key),
            items: $items,
            total: $total,
            chart: $chart,
        );
    }

    /**
     * @param  array<int, TrendPoint>  $points
     */
    protected function trend(
        string $key,
        array $points,
        StatFormat $format = StatFormat::Number,
        TrendChart $chart = TrendChart::Area,
        int $tone = 1,
    ): TrendWidget {
        return new TrendWidget(
            key: $key,
            label: $this->labelKey($key),
            points: $points,
            format: $format,
            chart: $chart,
            tone: $tone,
        );
    }

    /**
     * The i18n key of a widget label: `{domainCamel}.stats.{keyCamel}`
     * (e.g. `operational-sites` + `by_region` → `operationalSites.stats.byRegion`).
     * Always a KEY, never translated text (spec 0026, D-4).
     */
    protected function labelKey(string $key): string
    {
        return Str::camel($this->domain()).'.stats.'.Str::camel($key);
    }
}
