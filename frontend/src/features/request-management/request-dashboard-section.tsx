import { ChevronDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { ReportBarChart } from '@/components/ui/report-bar-chart'
import { StatCard } from '@/components/ui/stat-card'
import type {
  RequestDashboardCategory,
  RequestDashboardChart,
  RequestDashboardSummaryItem,
} from '@/features/request-management/dashboard-api'
import {
  OVERALL_SECTION_KEY,
  type RequestDashboardCollapse,
} from '@/features/request-management/use-request-dashboard-collapse'
import { cn } from '@/lib/utils'

/** Rung 2 of the surface scale (`ui-design.md` 1-bis): a panel that HOSTS cards, never `bg-card` itself. */
const SECTION_CLASS = 'flex flex-col gap-3 rounded-xl border bg-surface p-3'
const SUMMARY_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6'
const CHARTS_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2'
/** Mirrors `module-stats-panel.tsx`'s own Collapsible height animation. */
const COLLAPSIBLE_CONTENT_CLASS =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

function formatCount(value: number): string {
  return value.toLocaleString()
}

interface DashboardCollapsibleProps {
  title: string
  /** 2 for a section, 3 for a block inside it — the panel's own region label sits above both. */
  level: 2 | 3
  open: boolean
  onOpenChange: (open: boolean) => void
  children: React.ReactNode
  className?: string
}

/**
 * A titled, collapsible block. The trigger lives INSIDE the heading (a button
 * may not contain an `h2`, the reverse is valid): screen readers keep both the
 * heading level and the expanded/collapsed state, which Radix wires onto the
 * button itself.
 */
function DashboardCollapsible({
  title,
  level,
  open,
  onOpenChange,
  children,
  className,
}: DashboardCollapsibleProps) {
  const Heading = level === 2 ? 'h2' : 'h3'

  return (
    <Collapsible open={open} onOpenChange={onOpenChange} className={cn('flex flex-col gap-3', className)}>
      <Heading className={level === 2 ? 'text-sm font-semibold' : 'text-xs font-medium text-muted-foreground'}>
        <CollapsibleTrigger asChild>
          <button
            type="button"
            className="flex w-full items-center gap-1.5 rounded-md text-left outline-none hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
          >
            <ChevronDown
              aria-hidden="true"
              className={cn('size-3.5 shrink-0 transition-transform', open ? '' : '-rotate-90')}
            />
            <span className="truncate">{title}</span>
          </button>
        </CollapsibleTrigger>
      </Heading>

      <CollapsibleContent className={COLLAPSIBLE_CONTENT_CLASS}>{children}</CollapsibleContent>
    </Collapsible>
  )
}

function SummaryTiles({ items }: { items: RequestDashboardSummaryItem[] }) {
  return (
    <div className={SUMMARY_GRID_CLASS}>
      {items.map((item) => (
        <StatCard key={item.key} label={item.label} value={formatCount(item.value)} />
      ))}
    </div>
  )
}

interface DashboardOverallSectionProps {
  items: RequestDashboardSummaryItem[]
  collapse: RequestDashboardCollapse
}

/** The overall tiles over the union of the selected categories (D-8). */
export function DashboardOverallSection({ items, collapse }: DashboardOverallSectionProps) {
  const { t } = useTranslation()
  const title = t('requestManagement.dashboard.overall')

  return (
    <section className={SECTION_CLASS} aria-label={title}>
      <DashboardCollapsible
        title={title}
        level={2}
        open={collapse.isOpen(OVERALL_SECTION_KEY, 'section')}
        onOpenChange={(open) => collapse.setOpen(OVERALL_SECTION_KEY, 'section', open)}
      >
        <SummaryTiles items={items} />
      </DashboardCollapsible>
    </section>
  )
}

interface DashboardCategorySectionProps {
  category: RequestDashboardCategory
  collapse: RequestDashboardCollapse
}

/**
 * One category section (rev-3 D-10): the category's own tiles — every
 * indicator column, zeros included (D-11) — then its own charts, each block
 * collapsible on its own so the numbers can stay while the (much taller)
 * charts fold away. A section with no chart at all only happens in
 * `operators_only` when the category has no GA2 whatsoever, so the empty
 * message belongs here, per section.
 */
export function DashboardCategorySection({ category, collapse }: DashboardCategorySectionProps) {
  const { t } = useTranslation()
  const chartTitle = (chart: RequestDashboardChart): string =>
    chart.indicator_label ?? t('requestManagement.dashboard.indicatorsChartTitle')

  return (
    <section className={SECTION_CLASS} aria-label={category.label}>
      <DashboardCollapsible
        title={category.label}
        level={2}
        open={collapse.isOpen(category.key, 'section')}
        onOpenChange={(open) => collapse.setOpen(category.key, 'section', open)}
      >
        <div className="flex flex-col gap-3">
          <DashboardCollapsible
            title={t('requestManagement.dashboard.tilesTitle')}
            level={3}
            open={collapse.isOpen(category.key, 'tiles')}
            onOpenChange={(open) => collapse.setOpen(category.key, 'tiles', open)}
          >
            <SummaryTiles items={category.summary} />
          </DashboardCollapsible>

          {category.charts.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('requestManagement.dashboard.empty')}</p>
          ) : (
            <DashboardCollapsible
              title={t('requestManagement.dashboard.chartsTitle', { count: category.charts.length })}
              level={3}
              open={collapse.isOpen(category.key, 'charts')}
              onOpenChange={(open) => collapse.setOpen(category.key, 'charts', open)}
            >
              <div className={CHARTS_GRID_CLASS}>
                {category.charts.map((chart) => (
                  <ReportBarChart
                    key={chart.id}
                    title={chartTitle(chart)}
                    points={chart.points}
                    formatValue={formatCount}
                  />
                ))}
              </div>
            </DashboardCollapsible>
          )}
        </div>
      </DashboardCollapsible>
    </section>
  )
}
