import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { ReportBarChart } from '@/components/ui/report-bar-chart'
import { Skeleton } from '@/components/ui/skeleton'
import { StatCard } from '@/components/ui/stat-card'
import type { RequestDashboardChart, RequestDashboardData } from '@/features/request-management/dashboard-api'
import { RequestDashboardFilterBar } from '@/features/request-management/request-dashboard-filter-bar'
import { RequestReportFiltersDialog } from '@/features/request-management/request-report-filters-dialog'
import {
  isRequestReportQueryReady,
  toRequestReportFilterPayload,
} from '@/features/request-management/request-report-schema'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import { useRequestDashboard } from '@/features/request-management/use-request-dashboard'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { useRequestReportOperators } from '@/features/request-management/use-request-report-operators'
import {
  reconcileCategoryKeys,
  reconcileOperatorKeys,
  useRequestReportFilters,
} from '@/features/request-management/use-request-report-filters'
import { statsPanelId } from '@/features/stats/use-stats-panel'
import type { UseQueryResult } from '@tanstack/react-query'

/** Mirrors `module-stats-panel.tsx`'s own Collapsible height animation. */
const COLLAPSIBLE_CONTENT_CLASS =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

const SUMMARY_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4'
const CHARTS_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2'
const SKELETON_TILE_COUNT = 4

/** Hoisted so an unloaded operator list keeps a STABLE identity across renders. */
const EMPTY_KEYS: string[] = []

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
 * request is issued (AC-042).
 *
 * Owns the APPLIED filters (user directive 2026-09-08), restored from the
 * operator's last session by `useRequestReportFilters`. They are plain state,
 * not a `useForm()` instance: editing happens in `RequestReportFiltersDialog`,
 * which receives them and hands back a set already validated by the shared Zod
 * schema — so the query still gates on `isRequestReportQueryReady`, never on
 * unvalidated input. `RequestDashboardFilterBar` generates the CSV from the
 * same state, which is why nothing else may hold a copy of it.
 */
function RequestDashboardPanelBody() {
  const { filters, setFilters } = useRequestReportFilters()
  const [filtersOpen, setFiltersOpen] = useState(false)

  const categoriesQuery = useRequestReportCategories(true)
  const categories = categoriesQuery.data
  const operatorsQuery = useRequestReportOperators(true)
  const operators = operatorsQuery.data
  const operatorKeys = operators ? operators.map((operator) => operator.key) : EMPTY_KEYS

  // One shot per list load, so a background refetch can never wipe the user's
  // own picks: a restored selection keeps only the entries that still exist,
  // and an empty one falls back to all of them (rev-2 AC-050, spec 0109 D-11).
  const reconciledCategories = useRef(false)
  useEffect(() => {
    if (!categories || categories.length === 0 || reconciledCategories.current) {
      return
    }
    reconciledCategories.current = true
    setFilters((current) => reconcileCategoryKeys(current, categories))
  }, [categories, setFilters])

  const reconciledOperators = useRef(false)
  useEffect(() => {
    if (!operators || reconciledOperators.current) {
      return
    }
    reconciledOperators.current = true
    setFilters((current) => reconcileOperatorKeys(current, operators))
  }, [operators, setFilters])

  const filtersReady = isRequestReportQueryReady(filters, operatorKeys)
  // ONE normalization for both consumers (spec 0109 D-9): the charts fetch it
  // and the file is generated from it, so they cannot read the selection
  // differently. It is also the query key, so a changed selection is a
  // different cache entry.
  const payload = toRequestReportFilterPayload(filters, operatorKeys)
  const dashboardQuery = useRequestDashboard(payload, filtersReady)

  return (
    <div className="flex flex-col gap-4">
      <RequestDashboardFilterBar
        filters={filters}
        payload={payload}
        categoryCount={categories?.length ?? 0}
        operatorCount={operators?.length ?? 0}
        filtersReady={filtersReady}
        onEdit={() => setFiltersOpen(true)}
      />

      <DashboardResults query={dashboardQuery} />

      <RequestReportFiltersDialog
        open={filtersOpen}
        onOpenChange={setFiltersOpen}
        value={filters}
        onApply={setFilters}
      />
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
