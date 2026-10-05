import { useTranslation } from 'react-i18next'
import { ReportBarChart } from '@/components/ui/report-bar-chart'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import { compareIndicator, valuesByKey } from '@/features/request-management/dashboard-comparison'
import { buildLeaderboard } from '@/features/request-management/dashboard-leaderboard'
import { DashboardKpiCard } from '@/features/request-management/request-dashboard-kpi-card'
import { DashboardLeaderboard } from '@/features/request-management/request-dashboard-leaderboard'
import { KPI_GRID_CLASS } from '@/features/request-management/dashboard-overview'
import { cn } from '@/lib/utils'

function formatCount(value: number): string {
  return value.toLocaleString()
}

export interface DashboardCategoryPanelProps {
  category: RequestDashboardCategory
  /** The same category on the previous period (D-4); undefined while unavailable. */
  previous: RequestDashboardCategory | undefined
  /** Server label of the "Non assegnato" operator, from the operator list (key `unassigned`). */
  unassignedLabel: string | undefined
}

/**
 * One category tab (spec 0192 D-8): its own KPIs (only the columns THIS
 * category configures, zeros included — spec 0141 D-5), the indicator
 * profile chart and the operator ranking side by side. Which of the two
 * exists follows `row_mode`, exactly as the charts the server sends; a
 * category with no column configured keeps the two empty notices it always had.
 */
export function DashboardCategoryPanel({ category, previous, unassignedLabel }: DashboardCategoryPanelProps) {
  const { t } = useTranslation()
  const previousValues = valuesByKey(previous?.summary)
  const indicatorChart = category.charts.find((chart) => chart.scope === 'indicator')
  const leaderboard = buildLeaderboard(category.charts, unassignedLabel)

  return (
    <div className="flex flex-col gap-4">
      {category.summary.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('requestManagement.dashboard.tilesEmpty')}</p>
      ) : (
        <div className={KPI_GRID_CLASS}>
          {category.summary.map((item) => (
            <DashboardKpiCard
              key={item.key}
              item={item}
              trend={compareIndicator(item.key, item.value, previousValues?.get(item.key))}
            />
          ))}
        </div>
      )}

      {category.charts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('requestManagement.dashboard.empty')}</p>
      ) : (
        <div
          className={cn(
            'grid grid-cols-1 gap-3',
            indicatorChart && leaderboard ? 'xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]' : null,
          )}
        >
          {indicatorChart ? (
            <ReportBarChart
              title={t('requestManagement.dashboard.indicatorsChartTitle')}
              points={indicatorChart.points}
              formatValue={formatCount}
            />
          ) : null}
          {leaderboard ? (
            // Keyed by category: a new tab starts from its own default order, not the last one's.
            <DashboardLeaderboard key={category.key} leaderboard={leaderboard} />
          ) : null}
        </div>
      )}
    </div>
  )
}
