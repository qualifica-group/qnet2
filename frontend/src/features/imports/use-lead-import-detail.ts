import { useEntityDetail } from '@/hooks/use-entity-detail'
import { getImportRunSummary, getImportWizardRun } from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import { isResumableImportRun } from '@/features/imports/lead-import-status'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

/** The module is scoped to the leads domain only (spec 0034 scope: OUT). */
const LEADS_DOMAIN = 'leads'

/** Statuses the `summary` endpoint actually serves (spec 0034 AC-006: reviewing/completed/failed). */
const SUMMARY_STATUSES = new Set(['reviewing', 'completed', 'failed'])

export interface LeadImportDetailResult {
  run: ImportRunDetail | undefined
  summary: ImportRunSummaryReport | undefined
  isLoading: boolean
  isError: boolean
  /** The run fetch's raw error, so the page can tell a 404/403 apart from a transient failure. */
  error: unknown
  refetch: () => void
  summaryIsLoading: boolean
  summaryIsError: boolean
  /** Whether the wizard can still be resumed for this run (drives the "Resume" action). */
  isResumable: boolean
}

/**
 * Data of the lead-import detail page (spec 0034 AC-013). The run itself
 * (counters, mapping, review fields) is the same wizard `GET .../{run}`
 * contract already used to poll/resume the wizard, re-fetched fresh on open
 * like every other detail page (`useEntityDetail`). Its pre-confirm summary
 * (mapped columns, global config, dedup strategy) is fetched only once the
 * run is known to be in a state the backend actually serves it for — an
 * import still in `analyzing`/`configuring`/`staging`/`processing` has no
 * summary yet.
 */
export function useLeadImportDetail(runId: number | null): LeadImportDetailResult {
  const runEnabled = runId !== null
  const runQuery = useEntityDetail<ImportRunDetail>(
    importWizardKeys.run(LEADS_DOMAIN, runId ?? 0),
    () => getImportWizardRun(LEADS_DOMAIN, runId as number),
    runEnabled,
  )

  const summaryEnabled = runEnabled && !!runQuery.data && SUMMARY_STATUSES.has(runQuery.data.status)
  const summaryQuery = useEntityDetail<ImportRunSummaryReport>(
    importWizardKeys.summary(LEADS_DOMAIN, runId ?? 0),
    () => getImportRunSummary(LEADS_DOMAIN, runId as number),
    summaryEnabled,
  )

  return {
    run: runQuery.data,
    summary: summaryQuery.data,
    isLoading: runQuery.isLoading,
    isError: runQuery.isError,
    error: runQuery.error,
    refetch: runQuery.refetch,
    summaryIsLoading: summaryEnabled && summaryQuery.isLoading,
    summaryIsError: summaryEnabled && summaryQuery.isError,
    isResumable: runQuery.data ? isResumableImportRun(runQuery.data.status) : false,
  }
}
