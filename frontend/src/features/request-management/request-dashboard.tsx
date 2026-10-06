import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { Tabs, TabsContent } from '@/components/ui/tabs'
import type { RequestDashboardData } from '@/features/request-management/dashboard-api'
import type { DashboardDateRange } from '@/features/request-management/dashboard-period-presets'
import { UNASSIGNED_OPERATOR_KEY } from '@/features/request-management/dashboard-leaderboard'
import type {
  RequestReportCategory,
  RequestReportOperator,
  RequestReportSite,
} from '@/features/request-management/report-api'
import { DashboardCategoryPanel } from '@/features/request-management/request-dashboard-category-panel'
import { DashboardCategoryStrip } from '@/features/request-management/request-dashboard-category-strip'
import { RequestDashboardFilterBar } from '@/features/request-management/request-dashboard-filter-bar'
import { DashboardOverview } from '@/features/request-management/request-dashboard-overview'
import { DashboardNotice, DashboardSkeleton } from '@/features/request-management/request-dashboard-states'
import { RequestReportFiltersDialog } from '@/features/request-management/request-report-filters-dialog'
import {
  isCategoriesEmpty,
  isRequestReportQueryReady,
  toRequestReportFilterPayload,
} from '@/features/request-management/request-report-schema'
import { useRequestDashboard, useRequestDashboardPrevious } from '@/features/request-management/use-request-dashboard'
import { OVERVIEW_TAB, useRequestDashboardTab } from '@/features/request-management/use-request-dashboard-tab'
import { useRequestReportCategories } from '@/features/request-management/use-request-report-categories'
import { useRequestReportOperators } from '@/features/request-management/use-request-report-operators'
import { useRequestReportSites } from '@/features/request-management/use-request-report-sites'
import {
  reconcileCategoryKeys,
  reconcileOperatorKeys,
  reconcileSiteKeys,
  useRequestReportFilters,
} from '@/features/request-management/use-request-report-filters'
import type { UseQueryResult } from '@tanstack/react-query'

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

interface DashboardResultsProps {
  query: UseQueryResult<RequestDashboardData>
  previous: RequestDashboardData | undefined
  unassignedLabel: string | undefined
}

/**
 * The three states the dashboard can be in (spec 0107 AC-048): loading
 * skeleton, a retryable error, and the results as tabs (spec 0192 D-1) —
 * the overview, then one tab per returned category. Since rev-3 nothing is
 * dropped for being 0, so every selected category gets its tab: an empty
 * week is an answer. A filter change keeps the previous numbers on screen,
 * dimmed, until the new ones land.
 */
function DashboardResults({ query, previous, unassignedLabel }: DashboardResultsProps) {
  const { t } = useTranslation()
  const { data, error, isLoading, isError, isPlaceholderData, refetch } = query
  const categoryKeys = data ? data.categories.map((category) => category.key) : EMPTY_KEYS
  const { tab, setTab } = useRequestDashboardTab(categoryKeys)

  return (
    <div aria-busy={isLoading || isPlaceholderData} className="flex flex-col gap-3">
      {isLoading ? <DashboardSkeleton /> : null}

      {isError ? (
        <DashboardNotice tone="error" message={t(dashboardErrorKey(error))} onRetry={() => void refetch()} />
      ) : null}

      {data ? (
        <Tabs
          value={tab}
          onValueChange={setTab}
          className={isPlaceholderData ? 'gap-3 opacity-60 transition-opacity' : 'gap-3 transition-opacity'}
        >
          <DashboardCategoryStrip categories={data.categories} value={tab} onSelect={setTab} />

          <TabsContent value={OVERVIEW_TAB}>
            <DashboardOverview data={data} previous={previous} onSelectCategory={setTab} />
          </TabsContent>
          {data.categories.map((category) => (
            <TabsContent key={category.key} value={category.key}>
              <DashboardCategoryPanel
                category={category}
                previous={previous?.categories.find((candidate) => candidate.key === category.key)}
                unassignedLabel={unassignedLabel}
              />
            </TabsContent>
          ))}
        </Tabs>
      ) : null}
    </div>
  )
}

/**
 * Gestione Richieste's statistics dashboard (spec 0107 D-1, moved to its own
 * page by spec 0185, redesigned by spec 0192): deliberately NOT
 * `ModuleStatsPanel`/`GET /stats/{domain}` (spec 0026), which this module
 * never calls. Always rendered; the page that mounts it is gated by
 * `REQUEST_STATISTICS_PERMISSION`.
 *
 * Owns the APPLIED filters (user directive 2026-09-08), restored from the
 * operator's last session by `useRequestReportFilters`. They are plain state,
 * not a `useForm()` instance: editing happens in `RequestReportFiltersDialog`,
 * which receives them and hands back a set already validated by the shared Zod
 * schema, and the toolbar's one-click periods replace only the two dates —
 * so the query still gates on `isRequestReportQueryReady`, never on
 * unvalidated input. `RequestDashboardFilterBar` generates the CSV from the
 * same state, which is why nothing else may hold a copy of it.
 */
export function RequestDashboard() {
  const { t } = useTranslation()
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
  const previousData = useRequestDashboardPrevious(payload, filtersReady)
  const unassignedLabel = operators?.find((operator) => operator.key === UNASSIGNED_OPERATOR_KEY)?.label

  // A preset changes the dates only: every other filter stays as applied.
  const applyPeriod = (range: DashboardDateRange) => setFilters((current) => ({ ...current, ...range }))

  return (
    <section aria-label={t('requestManagement.dashboard.regionLabel')} className="flex flex-col gap-4">
      <RequestDashboardFilterBar
        filters={filters}
        payload={payload}
        categories={categories ?? EMPTY_CATEGORIES}
        sites={sites ?? EMPTY_SITES}
        operators={operators ?? EMPTY_OPERATORS}
        filtersReady={filtersReady}
        onEdit={() => setFiltersOpen(true)}
        onApplyPeriod={applyPeriod}
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
        <DashboardResults query={dashboardQuery} previous={previousData} unassignedLabel={unassignedLabel} />
      )}

      <RequestReportFiltersDialog
        open={filtersOpen}
        onOpenChange={setFiltersOpen}
        value={filters}
        onApply={setFilters}
      />
    </section>
  )
}
