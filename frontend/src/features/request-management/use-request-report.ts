import { useCallback, useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import axios from 'axios'
import {
  createRequestManagementReport,
  downloadRequestManagementReport,
  getRequestManagementReport,
  type CreateRequestReportPayload,
  type RequestReportRun,
  type RequestReportStatus,
} from '@/features/request-management/report-api'
import { requestManagementKeys } from '@/features/request-management/query-keys'

/** Interval (ms) between polls while the run is still processing. */
const POLL_INTERVAL_MS = 1500

/** Statuses that still require polling; `completed`/`failed` are terminal. */
const POLLING_STATUSES: ReadonlySet<RequestReportStatus> = new Set(['processing'])

/**
 * Maps a failed request onto a localized message (status-only, mirroring
 * `resolveExportErrorMessage`): no server message parsing, just the
 * well-known statuses this contract can return (403/422).
 */
export function resolveRequestReportErrorMessage(error: unknown, t: TFunction): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  if (status === 403) return t('requestManagement.report.errors.forbidden')
  if (status === 422) return t('requestManagement.report.errors.validation')
  return t('requestManagement.report.errors.generic')
}

/**
 * Orchestrates the create -> poll -> download cycle (spec 0106 D-8), modeled
 * on `features/exports/use-export.ts` without reusing it (that hook is tied
 * to `exportKeys` and the generic `/exports/{domain}` routes). Unlike that
 * flow the download is NOT a separate user action: confirming the form runs
 * the whole cycle and the file lands automatically once the run completes
 * (AC-045).
 */
export function useRequestReport() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(null)
  // Guards the auto-download against firing twice for the same run (e.g. a
  // stray refetch landing after the terminal status already triggered it).
  const downloadedRunId = useRef<number | null>(null)

  const createMutation = useMutation({
    mutationFn: (payload: CreateRequestReportPayload) => createRequestManagementReport(payload),
    onSuccess: (run) => {
      setRunId(run.id)
      queryClient.setQueryData<RequestReportRun>(requestManagementKeys.reportRun(run.id), run)
    },
  })

  const runQuery = useQuery({
    queryKey: requestManagementKeys.reportRun(runId),
    queryFn: () => getRequestManagementReport(runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update (including the `setQueryData` call
    // above), so creating the run starts polling without a separate effect.
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && POLLING_STATUSES.has(status) ? POLL_INTERVAL_MS : false
    },
  })

  const downloadMutation = useMutation({
    mutationFn: () => downloadRequestManagementReport(runId as number),
  })

  const reportRun = runId != null ? runQuery.data : undefined

  // React Query v5 dropped `onSuccess` from `useQuery`: reacting to the
  // polled status settling on `completed` — an imperative action, not a
  // fetch this effect performs itself — is the one thing the query alone
  // cannot express (AC-045-bis: `failed` must NOT trigger a download).
  useEffect(() => {
    if (reportRun?.status === 'completed' && downloadedRunId.current !== reportRun.id) {
      downloadedRunId.current = reportRun.id
      downloadMutation.mutate()
    }
  }, [reportRun, downloadMutation])

  const reset = useCallback(() => {
    setRunId(null)
    downloadedRunId.current = null
    createMutation.reset()
    downloadMutation.reset()
  }, [createMutation, downloadMutation])

  return {
    reportRun,
    create: createMutation.mutate,
    isCreating: createMutation.isPending,
    createError: createMutation.isError
      ? resolveRequestReportErrorMessage(createMutation.error, t)
      : null,
    isProcessing: reportRun?.status === 'processing',
    isDownloading: downloadMutation.isPending,
    downloadError: downloadMutation.isError
      ? resolveRequestReportErrorMessage(downloadMutation.error, t)
      : null,
    reset,
  }
}
