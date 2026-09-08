import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Form } from '@/components/ui/form'
import { ReportBarChart } from '@/components/ui/report-bar-chart'
import { Skeleton } from '@/components/ui/skeleton'
import { StatCard } from '@/components/ui/stat-card'
import type { RequestDashboardChart, RequestDashboardData } from '@/features/request-management/dashboard-api'
import { RequestReportFilters } from '@/features/request-management/request-report-filters'
import {
  buildRequestReportSchema,
  categoriesAreBlocked,
  isCategoriesEmpty,
  isRequestReportQueryReady,
  requestReportDefaultValues,
  type RequestReportFormValues,
} from '@/features/request-management/request-report-schema'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { useRequestDashboard } from '@/features/request-management/use-request-dashboard'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { statsPanelId } from '@/features/stats/use-stats-panel'
import type { UseQueryResult } from '@tanstack/react-query'

/** Mirrors `module-stats-panel.tsx`'s own Collapsible height animation. */
const COLLAPSIBLE_CONTENT_CLASS =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

const FILTERS_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4'
const SUMMARY_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4'
const CHARTS_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2'
const SKELETON_TILE_COUNT = 4

/** A chart's card title: the indicator alone for a per-category series, category + indicator for a per-operator one (rev-2 D-3). */
function chartTitle(chart: RequestDashboardChart): string {
  return chart.scope === 'operator' && chart.category_label
    ? `${chart.category_label} — ${chart.indicator_label}`
    : chart.indicator_label
}

function formatCount(value: number): string {
  return value.toLocaleString()
}

/** Placeholder rows shaped like the eventual tiles/charts, shown while the aggregates load. */
function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-3">
      <div className={SUMMARY_GRID_CLASS}>
        {Array.from({ length: SKELETON_TILE_COUNT }).map((_, index) => (
          <div key={index} className="flex flex-col gap-2 rounded-xl border bg-card p-3">
            <Skeleton className="h-3 w-16" />
            <Skeleton className="h-6 w-12" />
          </div>
        ))}
      </div>
      <Skeleton className="h-48 w-full sm:h-64" />
    </div>
  )
}

interface DashboardResultsProps {
  query: UseQueryResult<RequestDashboardData>
}

/**
 * The three states an open panel can be in (spec 0107 AC-048): loading
 * skeleton, an explicit empty message when `charts` is `[]` (AC-008's
 * all-zero charts are dropped server-side, so this is a legitimate outcome,
 * not an error), and a retryable error. Summary tiles render independently
 * of the charts' empty state — a branch can have every chart suppressed
 * while its totals are still meaningfully zero.
 */
function DashboardResults({ query }: DashboardResultsProps) {
  const { t } = useTranslation()
  const { data, isLoading, isError, refetch } = query

  return (
    <div aria-busy={isLoading} className="flex flex-col gap-3">
      {isLoading ? <DashboardSkeleton /> : null}

      {isError ? (
        <div className="flex flex-col items-start gap-2 rounded-xl border bg-card p-3">
          <p className="text-sm text-destructive" role="alert">
            {t('requestManagement.dashboard.loadError')}
          </p>
          <Button variant="outline" size="sm" onClick={() => void refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : null}

      {data && data.summary.length > 0 ? (
        <div className={SUMMARY_GRID_CLASS}>
          {data.summary.map((item) => (
            <StatCard key={item.key} label={item.label} value={formatCount(item.value)} />
          ))}
        </div>
      ) : null}

      {data && data.charts.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('requestManagement.dashboard.empty')}</p>
      ) : null}

      {data && data.charts.length > 0 ? (
        <div className={CHARTS_GRID_CLASS}>
          {data.charts.map((chart) => (
            <ReportBarChart key={chart.id} title={chartTitle(chart)} points={chart.points} formatValue={formatCount} />
          ))}
        </div>
      ) : null}
    </div>
  )
}

/**
 * The data-fetching body (spec 0107 D-5). Mounted by `<CollapsibleContent>`
 * only while the panel is open, mirroring `ModuleStatsPanelBody`: a
 * closed-at-load panel never mounts this, so nothing here ever runs and no
 * request is issued (AC-042). Owns its OWN `useForm()` instance — a sibling
 * of the CSV dialog's, never shared state — filled with the same defaults
 * (rev-2 D-4) and validated live (`mode: 'onChange'`) so the dashboard fetch
 * can gate on `formState.isValid` instead of a submit button that does not
 * exist here.
 */
function RequestDashboardPanelBody() {
  const { t } = useTranslation()
  const schema = buildRequestReportSchema(t)

  const form = useForm<RequestReportFormValues>({
    resolver: zodResolver(schema),
    defaultValues: requestReportDefaultValues(),
    mode: 'onChange',
  })

  const categoriesQuery = useRequestReportCategories(true)
  const categories = categoriesQuery.data
  const categoriesEmpty = isCategoriesEmpty(categories, categoriesQuery.isLoading, categoriesQuery.isError)
  const categoriesBlocked = categoriesAreBlocked(categoriesQuery.isLoading, categoriesQuery.isError, categoriesEmpty)

  // Same one-shot seeding as `RequestReportDialog` (rev-2 AC-050): every
  // branch selected by default, applied once per branch-list load.
  const seededFor = useRef(false)
  useEffect(() => {
    if (categories && categories.length > 0 && !seededFor.current) {
      seededFor.current = true
      form.reset({ ...form.getValues(), category_keys: categories.map((category) => category.key) })
    }
  }, [categories, form])

  const values = useWatch({ control: form.control }) as RequestReportFormValues
  const dashboardQuery = useRequestDashboard(values, isRequestReportQueryReady(values))

  return (
    <div className="flex flex-col gap-4">
      <Form {...form}>
        <div className={FILTERS_GRID_CLASS}>
          <RequestReportFilters
            control={form.control}
            categories={categories}
            categoriesLoading={categoriesQuery.isLoading}
            categoriesError={categoriesQuery.isError}
            categoriesEmpty={categoriesEmpty}
            disabled={categoriesBlocked}
          />
        </div>
      </Form>

      <DashboardResults query={dashboardQuery} />
    </div>
  )
}

export interface RequestDashboardPanelProps {
  isOpen: boolean
}

/**
 * Gestione Richieste' own dashboard panel (spec 0107 D-1): deliberately NOT
 * `ModuleStatsPanel`/`GET /stats/{domain}` (spec 0026), which this module
 * never calls. Opened by the SAME `StatsToggleButton`/`useStatsPanel` every
 * other module uses (`statsPanelId(REQUEST_MANAGEMENT_DOMAIN)` keeps
 * `aria-controls`/`id` in lockstep), wrapped in the same `Collapsible` for a
 * smooth height animation.
 */
export function RequestDashboardPanel({ isOpen }: RequestDashboardPanelProps) {
  const { t } = useTranslation()

  return (
    <Collapsible open={isOpen} onOpenChange={() => {}}>
      <CollapsibleContent
        id={statsPanelId(REQUEST_MANAGEMENT_DOMAIN)}
        role="region"
        aria-label={t('requestManagement.dashboard.regionLabel')}
        className={COLLAPSIBLE_CONTENT_CLASS}
      >
        <RequestDashboardPanelBody />
      </CollapsibleContent>
    </Collapsible>
  )
}
