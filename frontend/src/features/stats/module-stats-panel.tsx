import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Skeleton } from '@/components/ui/skeleton'
import { StatsWidgetView } from '@/features/stats/stats-widget'
import { useModuleStats } from '@/features/stats/use-module-stats'
import { statsPanelId } from '@/features/stats/use-stats-panel'
import type { StatsWidget } from '@/features/stats/types'

/** Tiles shown by the loading skeleton: the widest stat row of the grid. */
const SKELETON_TILE_COUNT = 4

const TILE_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4'
const CHART_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2'

/**
 * Height-animated open/close (Radix Collapsible, spec 0026): the existing
 * `collapsible-down`/`collapsible-up` keyframes from `tw-animate-css` (no new
 * keyframes). `motion-reduce` drops the animation entirely for users who asked
 * for reduced motion, per OS/browser preference.
 */
const COLLAPSIBLE_CONTENT_CLASS =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

interface WidgetGroups {
  tiles: StatsWidget[]
  charts: StatsWidget[]
}

/**
 * Splits the backend's widget list into the two responsive rows of the panel:
 * KPI tiles (4 columns at 1024px+) and charts (2 columns). The backend order is
 * preserved within each row. Unknown types stay in the chart row and render as
 * nothing (AC-012).
 */
function groupWidgets(widgets: StatsWidget[]): WidgetGroups {
  const tiles = widgets.filter((widget) => widget.type === 'stat')
  const charts = widgets.filter((widget) => widget.type !== 'stat')

  return { tiles, charts }
}

/** Placeholder row shaped like the KPI tiles, shown while the widgets load. */
function StatsPanelSkeleton() {
  return (
    <div className={TILE_GRID_CLASS}>
      {Array.from({ length: SKELETON_TILE_COUNT }).map((_, index) => (
        <div key={index} className="flex flex-col gap-2 rounded-xl border bg-card p-3">
          <Skeleton className="h-3 w-16" />
          <Skeleton className="h-6 w-12" />
        </div>
      ))}
    </div>
  )
}

interface ModuleStatsPanelBodyProps {
  domain: string
  showCharts: boolean
}

/**
 * The data-fetching body of the panel. Mounted by `<CollapsibleContent>` only
 * while the panel is open (or animating closed): a closed-at-load panel never
 * mounts this, so `useModuleStats` never runs and no request is issued
 * (AC-007). `useModuleStats` fires again on every later (re)mount, which is
 * exactly what makes a stats-invalidating mutation (create/update/delete)
 * refresh the KPIs the next time the user opens the panel.
 */
function ModuleStatsPanelBody({ domain, showCharts }: ModuleStatsPanelBodyProps) {
  const { t } = useTranslation()
  const { data, isPending, isError, refetch } = useModuleStats(domain)

  const groups = data ? groupWidgets(data.widgets) : null
  const charts = showCharts && groups ? groups.charts : []

  return (
    <div aria-busy={isPending} className="flex flex-col gap-3 pt-3">
      {isPending ? <StatsPanelSkeleton /> : null}

      {isError ? (
        <div className="flex flex-col items-start gap-2 rounded-xl border bg-card p-3">
          <p className="text-sm text-destructive" role="alert">
            {t('statsPanel.loadError')}
          </p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : null}

      {groups && groups.tiles.length === 0 && charts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('statsPanel.empty')}</p>
      ) : null}

      {groups && groups.tiles.length > 0 ? (
        <div className={TILE_GRID_CLASS}>
          {groups.tiles.map((widget) => (
            <StatsWidgetView key={widget.key} widget={widget} />
          ))}
        </div>
      ) : null}

      {charts.length > 0 ? (
        <div className={CHART_GRID_CLASS}>
          {charts.map((widget) => (
            <StatsWidgetView key={widget.key} widget={widget} />
          ))}
        </div>
      ) : null}
    </div>
  )
}

interface ModuleStatsPanelProps {
  /** Table domain of the module, e.g. `leads` (same key used by `<TableView>`). */
  domain: string
  isOpen: boolean
  /** `false` keeps only the KPI tiles (dashboard Task block, spec 0151 D-11). */
  showCharts?: boolean
}

/**
 * The one and only statistics panel (AC-011): it renders whatever widgets the
 * backend describes for `domain` and knows nothing about any module. Wrapped
 * in a `Collapsible` for a smooth height animation on open/close; its content
 * is only ever mounted while open (or animating shut), so a closed panel
 * issues no request (AC-007).
 */
export function ModuleStatsPanel({ domain, isOpen, showCharts = true }: ModuleStatsPanelProps) {
  const { t } = useTranslation()

  return (
    <Collapsible open={isOpen} onOpenChange={() => {}}>
      <CollapsibleContent
        id={statsPanelId(domain)}
        role="region"
        aria-label={t('statsPanel.regionLabel')}
        className={COLLAPSIBLE_CONTENT_CLASS}
      >
        <ModuleStatsPanelBody domain={domain} showCharts={showCharts} />
      </CollapsibleContent>
    </Collapsible>
  )
}
