import { useCallback, useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { fetchMassMigrationRun, startMassMigration } from '@/features/migrations/api'
import { migrationKeys } from '@/features/migrations/query-keys'
import { resolveMigrationErrorMessage } from '@/features/migrations/resolve-error-message'
import { useStallTimeout } from '@/hooks/use-stall-timeout'
import type { MassMigrationRun, MigrationRunStatus } from '@/features/migrations/types'

/** Interval (ms) between polls while the mass run is pending/processing. */
const POLL_INTERVAL_MS = 1500

/**
 * How long the aggregate run may stay in one status before the poll gives up
 * (ms). Same rationale as `useMigrationImport`: a queued job nobody consumes
 * never terminates. See `useStallTimeout`.
 */
const STALL_TIMEOUT_MS = 120_000

/** Statuses that still require polling; any other status is terminal. */
const POLLING_STATUSES: ReadonlySet<MigrationRunStatus> = new Set(['pending', 'processing'])

/**
 * Orchestrates the "Import all" flow (spec 0046): start the aggregate run, then
 * poll `GET /migrations/mass-runs/{id}` until it reaches a terminal status
 * (`completed`/`failed`, the latter meaning the chain stopped at a failing
 * source). Mirrors `useMigrationImport`; the dialog only renders from this state.
 */
export function useMassMigration() {
  const { t } = useTranslation('migrations')
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(null)

  const startMutation = useMutation({
    mutationFn: () => startMassMigration(),
    onSuccess: (run) => {
      setRunId(run.id)
      queryClient.setQueryData<MassMigrationRun>(migrationKeys.massRun(run.id), run)
    },
  })

  // Read by `refetchInterval`, which is evaluated before the stall state
  // exists in this render pass; the effect below keeps it in sync.
  const isStalledRef = useRef(false)

  const runQuery = useQuery({
    queryKey: runId != null ? migrationKeys.massRun(runId) : migrationKeys.idleMassRun,
    queryFn: () => fetchMassMigrationRun(runId as number),
    enabled: runId != null,
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
