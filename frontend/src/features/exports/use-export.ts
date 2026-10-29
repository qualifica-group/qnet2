import { useCallback, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import axios from 'axios'
import { createExport, downloadExport, getExportRun } from '@/features/exports/api'
import { exportKeys } from '@/features/exports/query-keys'
import i18n from '@/i18n'
import { forbiddenMessage } from '@/lib/forbidden-message'
import type { CreateExportPayload, ExportRun, ExportStatus } from '@/features/exports/types'

/** Interval (ms) between polls while the run is still processing. */
const POLL_INTERVAL_MS = 1500

/** Statuses that still require polling; any other status is terminal. */
const POLLING_STATUSES: ReadonlySet<ExportStatus> = new Set(['processing'])

/**
 * Maps a failed request onto a localized message (status-only, mirroring the
 * `resolveImportErrorMessage` convention in `features/imports/use-import.ts`):
 * no server message parsing, just the well-known statuses this contract can
 * return (403/422/429); a 403 carries the server's specific reason.
 */
export function resolveExportErrorMessage(error: unknown, t: TFunction): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  const forbidden = forbiddenMessage(error, i18n)
  if (forbidden) return forbidden
  if (status === 422) return t('exports.errors.validation')
  if (status === 429) return t('exports.errors.rateLimited')
  return t('exports.errors.generic')
}

interface UseExportArgs {
  /** Resource key selecting the backend `TableDefinition` (route segment). */
  domain: string
}

/**
 * Orchestrates the generic export flow: create → poll while `processing` →
 * terminal (`completed`/`failed`) → download. All business logic lives here;
 * `ExportDialog`/`ExportProgress` only render from the returned state.
 */
export function useExport({ domain }: UseExportArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(null)

  const createMutation = useMutation({
    mutationFn: (payload: CreateExportPayload) => createExport(domain, payload),
    onSuccess: (exportRun) => {
      setRunId(exportRun.id)
      queryClient.setQueryData<ExportRun>(exportKeys.run(domain, exportRun.id), exportRun)
    },
  })

  const runQuery = useQuery({
    queryKey: runId != null ? exportKeys.run(domain, runId) : exportKeys.domain(domain),
    queryFn: () => getExportRun(domain, runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update (including the `setQueryData` call
    // above), so creating the run starts polling without a separate effect.
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && POLLING_STATUSES.has(status) ? POLL_INTERVAL_MS : false
    },
  })

  const downloadMutation = useMutation({
    mutationFn: () => downloadExport(domain, runId as number),
  })

  /** Clears the run and both mutation states, e.g. when the dialog closes. */
  const reset = useCallback(() => {
    setRunId(null)
    createMutation.reset()
    downloadMutation.reset()
  }, [createMutation, downloadMutation])

  return {
    exportRun: runId != null ? runQuery.data : undefined,
    create: createMutation.mutate,
    isCreating: createMutation.isPending,
    createError: createMutation.isError
      ? resolveExportErrorMessage(createMutation.error, t)
      : null,
    download: downloadMutation.mutate,
    isDownloading: downloadMutation.isPending,
    downloadError: downloadMutation.isError
      ? resolveExportErrorMessage(downloadMutation.error, t)
      : null,
    reset,
  }
}
