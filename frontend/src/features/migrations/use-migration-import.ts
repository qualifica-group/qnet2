import { useCallback, useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { fetchMigrationRun, startMigrationImport } from '@/features/migrations/api'
import { migrationKeys } from '@/features/migrations/query-keys'
import { resolveMigrationErrorMessage } from '@/features/migrations/resolve-error-message'
import { useStallTimeout } from '@/hooks/use-stall-timeout'
import type { MigrationRun, MigrationRunCreated, MigrationRunStatus } from '@/features/migrations/types'

/** Interval (ms) between polls while the run is pending/processing. */
const POLL_INTERVAL_MS = 1500

/**
 * How long the run may stay in one status before the poll gives up (ms). The
 * run is a queued job: with no worker consuming the queue it never reaches a
 * terminal status and the dialog would poll forever. See `useStallTimeout`.
 */
const STALL_TIMEOUT_MS = 120_000

/** Statuses that still require polling; any other status is a terminal point. */
const POLLING_STATUSES: ReadonlySet<MigrationRunStatus> = new Set(['pending', 'processing'])

/** Seeds the polling cache from the 201 response, before the first poll resolves. */
function migrationRunFromCreated(created: MigrationRunCreated): MigrationRun {
  return {
    id: created.id,
    source: created.source,
    status: created.status,
    total_rows: created.total_rows,
    created_rows: created.created_rows,
    skipped_rows: created.skipped_rows,
    failed_rows: created.failed_rows,
    report: null,
    created_at: created.created_at,
  }
}

interface UseMigrationImportArgs {
  /** Source key selecting the backend `MigrationSource` (route segment). */
  source: string
}

/**
 * Orchestrates the import (fase 2) flow for a single source: starts the
 * queued run, then polls `GET .../runs/{id}` until it reaches a terminal
 * status (`completed`/`failed`). All business logic lives here; the dialog
 * components only render from the returned state.
 */
export function useMigrationImport({ source }: UseMigrationImportArgs) {
  const { t } = useTranslation('migrations')
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(null)

  const startMutation = useMutation({
    mutationFn: () => startMigrationImport(source),
    onSuccess: (created) => {
      setRunId(created.id)
      queryClient.setQueryData<MigrationRun>(
        migrationKeys.run(source, created.id),
        migrationRunFromCreated(created),
      )
    },
  })

  // Read by `refetchInterval`, which is evaluated before the stall state
  // exists in this render pass; the effect below keeps it in sync.
  const isStalledRef = useRef(false)

  const runQuery = useQuery({
    queryKey: runId != null ? migrationKeys.run(source, runId) : migrationKeys.idleRun(source),
    queryFn: () => fetchMigrationRun(source, runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update (including the `setQueryData` seed
    // above), so the poll starts immediately after the run is created.
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (!status || !POLLING_STATUSES.has(status)) return false
      return isStalledRef.current ? false : POLL_INTERVAL_MS
    },
  })

  const polledRun = runId != null ? runQuery.data : undefined
  const pollingPhase =
    polledRun && POLLING_STATUSES.has(polledRun.status) ? `${polledRun.id}:${polledRun.status}` : null
  const { isStalled: isPollingStalled, resetStall } = useStallTimeout(pollingPhase, STALL_TIMEOUT_MS)

  useEffect(() => {
    isStalledRef.current = isPollingStalled
  }, [isPollingStalled])

  const { refetch: refetchRun } = runQuery

  /** Gives the stuck run a fresh window and re-reads it right away. */
  const retryPolling = useCallback(() => {
    resetStall()
    void refetchRun()
  }, [resetStall, refetchRun])

  /** Clears the run and mutation state, e.g. when the dialog closes. */
  const reset = useCallback(() => {
    setRunId(null)
    startMutation.reset()
  }, [startMutation])

  return {
    run: runId != null ? runQuery.data : undefined,
    start: startMutation.mutate,
    isStarting: startMutation.isPending,
    startError: startMutation.isError
      ? resolveMigrationErrorMessage(startMutation.error, t)
      : null,
    isPollingStalled,
    retryPolling,
    reset,
  }
}
