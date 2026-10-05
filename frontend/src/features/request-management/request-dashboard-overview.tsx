import { useTranslation } from 'react-i18next'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import { compareIndicator, valuesByKey } from '@/features/request-management/dashboard-comparison'
import { buildHeatmap, categoryShares, KPI_GRID_CLASS } from '@/features/request-management/dashboard-overview'
import { DashboardHeatmap } from '@/features/request-management/request-dashboard-heatmap'
import { DashboardKpiCard } from '@/features/request-management/request-dashboard-kpi-card'

export interface DashboardOverviewProps {
  data: RequestDashboardData
  /** The same dashboard on the previous period (D-4); undefined while unavailable. */
  previous: RequestDashboardData | undefined
  onSelectCategory: (categoryKey: string) => void
}

/**
 * The "Panoramica" tab (spec 0192 D-5/D-6): the overall KPIs over the union
 * of the selected categories, each with its change and its split by
 * category, then the categories x indicators heatmap. Each split bar names
 * its largest contributor in plain text, so it never relies on color alone.
 */
export function DashboardOverview({ data, previous, onSelectCategory }: DashboardOverviewProps) {
  const { t } = useTranslation()
  const previousValues = valuesByKey(previous?.summary)
  const heatmap = buildHeatmap(data.categories)
  const othersLabel = t('requestManagement.dashboard.shares.others')

  return (
    <div className="flex flex-col gap-4">
      {data.summary.length > 0 ? (
        <section aria-labelledby="request-dashboard-overall" className="flex flex-col gap-3">
          <h2 id="request-dashboard-overall" className="text-sm font-semibold">
            {t('requestManagement.dashboard.overall')}
          </h2>
          <div className={KPI_GRID_CLASS}>
            {data.summary.map((item) => (
              <DashboardKpiCard
                key={item.key}
                item={item}
                trend={compareIndicator(item.key, item.value, previousValues?.get(item.key))}
                shares={data.categories.length > 1 ? categoryShares(data.categories, item.key, othersLabel) : undefined}
              />
            ))}
          </div>
        </section>
      ) : null}

      {heatmap.columns.length > 0 ? <DashboardHeatmap heatmap={heatmap} onSelectCategory={onSelectCategory} /> : null}
    </div>
  )
}
