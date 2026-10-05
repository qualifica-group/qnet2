import type { CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronRight } from 'lucide-react'
import { Card } from '@/components/ui/card'
import { type Heatmap, heatIntensity } from '@/features/request-management/dashboard-overview'
import { cn } from '@/lib/utils'

/** Fill strength (percent of `--primary`) at the column maximum; the floor keeps a non-zero cell visible. */
const HEAT_MAX_PERCENT = 85
const HEAT_MIN_PERCENT = 8
/** Above this intensity the cell is dark enough to need the primary foreground for AA contrast. */
const HEAT_INVERT_THRESHOLD = 0.55

function cellStyle(value: number, max: number): CSSProperties | undefined {
  if (value <= 0) {
    return undefined
  }
  const percent = HEAT_MIN_PERCENT + (HEAT_MAX_PERCENT - HEAT_MIN_PERCENT) * heatIntensity(value, max)

  return { backgroundColor: `color-mix(in oklch, var(--primary) ${percent.toFixed(0)}%, transparent)` }
}

export interface DashboardHeatmapProps {
  heatmap: Heatmap
  onSelectCategory: (categoryKey: string) => void
}

/**
 * Categories x indicators at a glance (spec 0192 D-5). Each column is
 * shaded against ITS OWN maximum — phone calls and enrolments live on
 * different scales — and the number is always printed, so the shade is a
 * reading aid, never the only carrier of the value. The category button in
 * each row (and a click anywhere on it) opens that category's tab.
 */
export function DashboardHeatmap({ heatmap, onSelectCategory }: DashboardHeatmapProps) {
  const { t } = useTranslation()

  return (
    <Card className="gap-3 py-3">
      <div className="flex flex-col gap-0.5 px-3">
        <h3 className="text-sm font-semibold">{t('requestManagement.dashboard.heatmap.title')}</h3>
        <p className="text-xs text-muted-foreground">{t('requestManagement.dashboard.heatmap.description')}</p>
      </div>
      <div className="w-0 min-w-full overflow-x-auto px-3">
        <table className="w-full border-separate border-spacing-1 text-xs">
          <caption className="sr-only">{t('requestManagement.dashboard.heatmap.title')}</caption>
          <thead>
            <tr>
              <th scope="col" className="sticky left-0 z-10 bg-card px-2 py-1 text-left font-medium text-muted-foreground">
                {t('requestManagement.dashboard.heatmap.category')}
              </th>
              {heatmap.columns.map((column) => (
                <th
                  key={column.key}
                  scope="col"
                  title={column.label}
                  className="max-w-28 min-w-20 px-1 py-1 text-center align-bottom font-medium text-muted-foreground"
                >
                  <span className="line-clamp-2">{column.label}</span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {heatmap.rows.map((row) => (
              <tr key={row.key} className="group cursor-pointer" onClick={() => onSelectCategory(row.key)}>
                <th scope="row" className="sticky left-0 z-10 bg-card p-0 text-left font-medium">
                  <button
                    type="button"
                    onClick={(event) => {
                      event.stopPropagation()
                      onSelectCategory(row.key)
                    }}
                    className="flex w-full min-w-28 items-center gap-1.5 rounded-md px-2 py-1.5 outline-none group-hover:bg-muted focus-visible:ring-[2px] focus-visible:ring-ring/50"
                  >
                    <span className="truncate">{row.label}</span>
                    <ChevronRight
                      aria-hidden="true"
                      className="ml-auto size-3.5 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                    />
                  </button>
                </th>
                {row.cells.map((value, index) => {
                  const column = heatmap.columns[index]
                  if (value === null) {
                    return (
                      <td
                        key={column.key}
                        className="rounded-md px-2 py-1.5 text-center text-muted-foreground"
                        title={t('requestManagement.dashboard.heatmap.notConfigured')}
                      >
                        <span aria-hidden="true">—</span>
                        <span className="sr-only">{t('requestManagement.dashboard.heatmap.notConfigured')}</span>
                      </td>
                    )
                  }
                  const strong = heatIntensity(value, column.max) > HEAT_INVERT_THRESHOLD

                  return (
                    <td
                      key={column.key}
                      style={cellStyle(value, column.max)}
                      className={cn(
                        'rounded-md px-2 py-1.5 text-center tabular-nums',
                        value === 0
                          ? 'text-muted-foreground'
                          : 'font-medium transition-transform group-hover:scale-[1.03] motion-reduce:transition-none',
                        strong ? 'text-primary-foreground' : value > 0 ? 'text-foreground' : null,
                      )}
                    >
                      {value.toLocaleString()}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
