import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible'
import { Skeleton } from '@/components/ui/skeleton'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import type {
  RequestReportCategory,
  RequestReportOperator,
  RequestReportSite,
} from '@/features/request-management/report-api'
import { RequestDashboardFilterBar } from '@/features/request-management/request-dashboard-filter-bar'
import {
  DashboardCategorySection,
  DashboardOverallSection,
} from '@/features/request-management/request-dashboard-section'
import { RequestReportFiltersDialog } from '@/features/request-management/request-report-filters-dialog'
import {
  isCategoriesEmpty,
  isRequestReportQueryReady,
  toRequestReportFilterPayload,
} from '@/features/request-management/request-report-schema'
import { useRequestModule } from '@/features/request-management/request-module'
import { useRequestDashboard } from '@/features/request-management/use-request-dashboard'
import {
  dashboardCollapseTargets,
  type RequestDashboardCollapse,
  useRequestDashboardCollapse,
} from '@/features/request-management/use-request-dashboard-collapse'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { useRequestReportOperators } from '@/features/request-management/use-request-report-operators'
import { useRequestReportSites } from '@/features/request-management/use-request-report-sites'
import {
  reconcileCategoryKeys,
  reconcileOperatorKeys,
  reconcileSiteKeys,
  useRequestReportFilters,
} from '@/features/request-management/use-request-report-filters'
import { statsPanelId } from '@/features/stats/use-stats-panel'
import type { UseQueryResult } from '@tanstack/react-query'

/** Mirrors `module-stats-panel.tsx`'s own Collapsible height animation. */
const COLLAPSIBLE_CONTENT_CLASS =
  'overflow-hidden data-[state=open]:animate-collapsible-down data-[state=closed]:animate-collapsible-up motion-reduce:animate-none'

const SKELETON_GRID_CLASS = 'grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6'
const SKELETON_TILE_COUNT = 4

/** Hoisted so an unloaded operator/site list keeps a STABLE identity across renders. */
const EMPTY_KEYS: string[] = []
const EMPTY_CATEGORIES: RequestReportCategory[] = []
const EMPTY_SITES: RequestReportSite[] = []
const EMPTY_OPERATORS: RequestReportOperator[] = []

const HTTP_FORBIDDEN = 403
const HTTP_UNPROCESSABLE = 422

/**
 * Picks the message that tells the operator WHY the dashboard is missing and
 * what to do about it, instead of one catch-all line: no response at all, no
 * permission, filters the server rejects, or a server-side failure.
 */
function dashboardErrorKey(error: unknown): string {
  if (!axios.isAxiosError(error)) {
    return 'requestManagement.dashboard.errors.generic'
  }
  if (!error.response) {
    return 'requestManagement.dashboard.errors.network'
  }
  if (error.response.status === HTTP_FORBIDDEN) {
    return 'requestManagement.dashboard.errors.forbidden'
  }
  if (error.response.status === HTTP_UNPROCESSABLE) {
    return 'requestManagement.dashboard.errors.invalidFilters'
  }
  return 'requestManagement.dashboard.errors.generic'
}

interface DashboardNoticeProps {
  message: string
  tone: 'error' | 'info'
  onRetry?: () => void
}

/** One notice box for every "nothing to chart" outcome, retryable when a refetch can help. */
function DashboardNotice({ message, tone, onRetry }: DashboardNoticeProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-start gap-2 rounded-xl border bg-card p-3">
      <p
        className={tone === 'error' ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}
        role={tone === 'error' ? 'alert' : 'status'}
      >
        {message}
      </p>
      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      ) : null}
    </div>
  )
}

/** Placeholder rows shaped like the eventual tiles/charts, shown while the aggregates load. */
function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-3">
      <div className={SKELETON_GRID_CLASS}>
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
  collapse: RequestDashboardCollapse
}

/**
 * The three states an open panel can be in (spec 0107 AC-048): loading
 * skeleton, a retryable error, and the results — the overall tiles over the
 * union of the selected categories (D-8), then one section per category
 * (rev-3 D-10). Since rev-3 nothing is dropped for being 0, so a section is
 * always rendered for every selected category: an empty week is an answer.
 *
 * Every section, and the two blocks inside it, fold independently; the state
 * is owned by the panel body (one hook for the whole panel) rather than per
 * section, so it survives a section unmounting on a refetch and is persisted
 * in a single storage entry (user directive 2026-09-08).
 */
function DashboardResults({ query, collapse }: DashboardResultsProps) {
  const { t } = useTranslation()
  const { data, error, isLoading, isError, refetch } = query

  return (
    <div aria-busy={isLoading} className="flex flex-col gap-3">
      {isLoading ? <DashboardSkeleton /> : null}

      {isError ? (
        <DashboardNotice tone="error" message={t(dashboardErrorKey(error))} onRetry={() => void refetch()} />
      ) : null}

      {data && data.summary.length > 0 ? (
        <DashboardOverallSection items={data.summary} collapse={collapse} />
      ) : null}

      {data?.categories.map((category) => (
        <DashboardCategorySection key={category.key} category={category} collapse={collapse} />
      ))}
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
  const { t } = useTranslation()
  const module = useRequestModule()
  const { filters, setFilters } = useRequestReportFilters()
  const [filtersOpen, setFiltersOpen] = useState(false)

  const categoriesQuery = useRequestReportCategories(true)
  const categories = categoriesQuery.data
  const operatorsQuery = useRequestReportOperators(true)
  const operators = operatorsQuery.data
  const operatorKeys = operators ? operators.map((operator) => operator.key) : EMPTY_KEYS
  const sitesQuery = useRequestReportSites(true)
  const sites = sitesQuery.data
  const siteKeys = sites ? sites.map((site) => site.key) : EMPTY_KEYS

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

  // Its OWN effect, independent of the operator one (spec 0112): both can land
  // in the same commit, and only the updater form of `setFilters` keeps the
  // second write from clobbering the first with a stale copy.
  const reconciledSites = useRef(false)
  useEffect(() => {
    if (!sites || reconciledSites.current) {
      return
    }
    reconciledSites.current = true
    setFilters((current) => reconcileSiteKeys(current, sites))
  }, [sites, setFilters])

  // No request is visible in any reportable category: there is nothing to
  // chart, and a selection restored from an earlier session would only be
  // rejected by the server's allow-list. Say so instead of issuing it.
  const categoriesEmpty = isCategoriesEmpty(categories, categoriesQuery.isLoading, categoriesQuery.isError)
  // Until the restored selection is reconciled against the loaded list, a key
  // the report no longer offers would 422: the query waits for that commit.
  const categoryKeysKnown =
    categories !== undefined &&
    filters.category_keys.every((key) => categories.some((category) => category.key === key))
  const filtersReady = categoryKeysKnown && isRequestReportQueryReady(filters, operatorKeys, siteKeys)
  // ONE normalization for both consumers (spec 0109 D-9): the charts fetch it
  // and the file is generated from it, so they cannot read the selection
  // differently. It is also the query key, so a changed selection is a
  // different cache entry.
  const payload = toRequestReportFilterPayload(filters, operatorKeys, siteKeys)
  const dashboardQuery = useRequestDashboard(payload, filtersReady)
  // Held here, not in `DashboardResults`, so the bar's expand/collapse-all
  // button and the sections read and write the SAME state.
  const collapse = useRequestDashboardCollapse()
  const collapseTargets = dashboardCollapseTargets(dashboardQuery.data)
  const allExpanded = collapse.areAllOpen(collapseTargets)

  return (
    <div className="flex flex-col gap-4">
      <RequestDashboardFilterBar
        reportPermission={module.permission('report')}
        filters={filters}
        payload={payload}
        categories={categories ?? EMPTY_CATEGORIES}
        sites={sites ?? EMPTY_SITES}
        operators={operators ?? EMPTY_OPERATORS}
        filtersReady={filtersReady}
        onEdit={() => setFiltersOpen(true)}
        allExpanded={allExpanded}
        canToggleExpanded={collapseTargets.length > 0}
        onToggleExpanded={() => collapse.setAllOpen(collapseTargets, !allExpanded)}
      />

      {categoriesQuery.isError ? (
        <DashboardNotice
          tone="error"
          message={t('requestManagement.report.errors.categoriesLoadFailed')}
          onRetry={() => void categoriesQuery.refetch()}
        />
      ) : categoriesEmpty ? (
        <DashboardNotice tone="info" message={t('requestManagement.dashboard.noCategories')} />
      ) : (
        <DashboardResults query={dashboardQuery} collapse={collapse} />
      )}

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
  const module = useRequestModule()

  return (
    <Collapsible open={isOpen} onOpenChange={() => {}}>
      <CollapsibleContent
        id={statsPanelId(module.key)}
        role="region"
        aria-label={t('requestManagement.dashboard.regionLabel')}
        className={COLLAPSIBLE_CONTENT_CLASS}
      >
        <RequestDashboardPanelBody />
      </CollapsibleContent>
    </Collapsible>
  )
}
