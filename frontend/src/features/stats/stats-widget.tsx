import { useTranslation } from 'react-i18next'
import { StatBarList } from '@/components/ui/stat-bar-list'
import { StatCard } from '@/components/ui/stat-card'
import { StatChart } from '@/components/ui/stat-chart'
import { StatColumnChart } from '@/components/ui/stat-column-chart'
import { StatDonutChart } from '@/components/ui/stat-donut-chart'
import { StatStackedBar } from '@/components/ui/stat-stacked-bar'
import { groupDistributionSeries } from '@/features/stats/distribution-series'
import { formatStatValue, statSeriesFormatter } from '@/features/stats/format-stat-value'
import { formatTrendLabel } from '@/features/stats/format-trend-label'
import {
  normalizeChartTone,
  normalizeDistributionChart,
  normalizeTrendChart,
} from '@/features/stats/normalize-chart-variant'
import { resolveDistributionColor } from '@/features/stats/resolve-distribution-color'
import { resolveStatsIcon } from '@/features/stats/stats-icons'
import type { DistributionWidget, StatsWidget, TrendWidget } from '@/features/stats/types'

interface StatsWidgetViewProps {
  widget: StatsWidget
}

/** Renders a `distribution` widget with the shape its `chart` field selects (spec 0152 D-2). */
function DistributionWidgetView({ widget }: { widget: DistributionWidget }) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language
  const title = t(widget.label)
  const formatValue = statSeriesFormatter('number', locale)
  const emptyLabel = t('statsPanel.noData')
  const variant = normalizeDistributionChart(widget.chart)

  if (variant === 'bars') {
    return (
      <StatBarList
        title={title}
        items={widget.items.map((item) => ({ ...item, color: resolveDistributionColor(item.color) }))}
        total={widget.total}
        formatValue={formatValue}
        emptyLabel={emptyLabel}
      />
    )
  }

  const resolvedItems = groupDistributionSeries(widget.items, t('statsPanel.others'))

  if (variant === 'columns') {
    return <StatColumnChart title={title} items={resolvedItems} formatValue={formatValue} emptyLabel={emptyLabel} />
  }

  if (variant === 'stacked') {
    return (
      <StatStackedBar
        title={title}
        items={resolvedItems}
        total={widget.total}
        formatValue={formatValue}
        emptyLabel={emptyLabel}
      />
    )
  }

  return (
    <StatDonutChart
      title={title}
      items={resolvedItems}
      total={widget.total}
      formatValue={formatValue}
      emptyLabel={emptyLabel}
    />
  )
}

/** Renders a `trend` widget with the shape/tone its `chart`/`tone` fields select (spec 0152 D-3/D-4). */
function TrendWidgetView({ widget }: { widget: TrendWidget }) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language

  return (
    <StatChart
      title={t(widget.label)}
      points={widget.points.map((point) => ({ ...point, label: formatTrendLabel(point.label, locale) }))}
      formatValue={statSeriesFormatter(widget.format, locale)}
      emptyLabel={t('statsPanel.noData')}
      variant={normalizeTrendChart(widget.chart)}
      tone={normalizeChartTone(widget.tone)}
    />
  )
}

/**
 * Renders one backend-described widget with the matching design-system
 * component. An unknown `type` renders nothing (AC-012): a backend newer than
 * the deployed frontend must never break the page.
 */
export function StatsWidgetView({ widget }: StatsWidgetViewProps) {
  const { t, i18n } = useTranslation()
  const locale = i18n.language

  switch (widget.type) {
    case 'stat':
      return (
        <StatCard
          label={t(widget.label)}
          value={formatStatValue(widget.value, widget.format, locale)}
          subtitle={
            widget.subtitle ? t(widget.subtitle.key, { count: widget.subtitle.count }) : undefined
          }
          icon={resolveStatsIcon(widget.icon)}
        />
      )
    case 'distribution':
      return <DistributionWidgetView widget={widget} />
    case 'trend':
      return <TrendWidgetView widget={widget} />
    default:
      return null
  }
}
