import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import axios from 'axios'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  analyzeImport,
  confirmImportRun,
  configureImportRun,
  createMappingTemplate,
  getImportWizardRun,
} from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import { useStallTimeout } from '@/hooks/use-stall-timeout'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type {
  ConfigureImportPayload,
  ConfirmImportPayload,
  ImportRunDetail,
  ImportRunStatus,
  ImportRunSummary,
} from '@/features/imports/wizard/types'

/** Interval (ms) between polls while the run is analyzing/staging/processing. */
const POLL_INTERVAL_MS = 1500

/**
 * How long a single phase may run before the poll gives up (ms). Every phase
 * waits on a queued job: with no worker consuming the queue the status never
 * moves and the wizard would poll forever. Generous enough that a genuinely
 * slow staging/commit of a large file is not cut short.
 */
const STALL_TIMEOUT_MS = 120_000

/** Statuses that still require polling; any other status is a pause/terminal point. */
const POLLING_STATUSES: ReadonlySet<ImportRunStatus> = new Set(['analyzing', 'staging', 'processing'])

/**
 * HTTP statuses a step transition returns when the server refuses the move.
 * The backend's status guards (`ImportService::configure`/`confirmStaged`)
 * answer 422, so the same code covers both a stale client view of the run and
 * a genuine payload rejection — re-reading the run is correct either way: it
 * either resyncs the wizard onto the real step, or confirms the current one.
 */
const STALE_STATE_HTTP_STATUSES: ReadonlySet<number> = new Set([409, 422])

function isStaleStateError(error: unknown): boolean {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  return status != null && STALE_STATE_HTTP_STATUSES.has(status)
}

/** Index into the 4-step stepper (upload/mapping+config/review/summary). */
export type WizardStepIndex = 0 | 1 | 2 | 3

function isWizardStepIndex(value: number): value is WizardStepIndex {
  return value === 0 || value === 1 || value === 2 || value === 3
}

/**
 * Derives the step to render from the run's server-authoritative `status`
 * (spec 0033 AC-025: the wizard resumes from `GET run`, never from client
 * state alone). The `configuring` status covers the upload-summary gate and
 * the mapping+config step (nothing is persisted until that step's submit);
 * `reviewing` covers both review and summary (summary is a read-only report
 * over the same staged rows, confirmed only from there), disambiguated by
 * `localStep`.
 */
function deriveStepIndex(status: ImportRunStatus | undefined, localStep: WizardStepIndex): WizardStepIndex {
  switch (status) {
    case 'analyzing':
      return 0
    case 'configuring':
      return localStep === 0 ? 0 : 1
    case 'staging':
      return 2
    case 'reviewing':
      return localStep === 3 ? 3 : 2
    case 'processing':
    case 'completed':
    case 'failed':
      return 3
    default:
      return 0
  }
}

/** Reads a global-config value from the run as a relation id, or its field default. */
function resolveGlobalConfigValue(
  run: ImportRunDetail,
  fieldId: string,
  fieldDefault: string | number | null,
): number | null {
  const stored = run.global_config?.[fieldId]
  if (typeof stored === 'number') return stored
  return typeof fieldDefault === 'number' ? fieldDefault : null
}

interface UseImportWizardArgs {
  /** Resource key selecting the backend `ImportDefinition` (route segment). */
  domain: string
  /** Run id resumed from the page's `?runId=` query param, or `null` for a fresh wizard. */
  initialRunId: number | null
  /** Called once a fresh upload creates a run, so the caller can persist it in the URL. */
  onRunCreated?: (runId: number) => void
}

/**
 * Orchestrates the advanced import wizard's state machine (spec 0033): the
 * run's `status` (server-authoritative) drives which step renders; upload,
 * configure and confirm are mutations that move the run forward; polling
 * covers the two async backend phases (analyzing, staging, processing).
 * Config/mapping drafts are held locally until the mapping step's single
 * submit persists both together (the backend only exposes one `configure`
 * endpoint for column_mapping + global_config + dedup_strategy combined).
 */
export function useImportWizard({ domain, initialRunId, onRunCreated }: UseImportWizardArgs) {
  const { t } = useTranslation('importWizard')
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(initialRunId)
  // A resumed run (initialRunId set) skips the "review analysis, then
  // continue" gate of a fresh upload — see `deriveStepIndex`'s `analyzing`
  // sibling handling in `ImportStepUpload`.
  const [localStep, setLocalStep] = useState<WizardStepIndex>(initialRunId != null ? 1 : 0)

  // Read by `refetchInterval` below, which is evaluated before the stall
  // state exists in this render pass; the effect further down keeps it in
  // sync, so a stall stops the poll at its next evaluation (one poll later
  // at most) without any render-time ref write.
  const isStalledRef = useRef(false)

  const runQuery = useQuery({
    queryKey: runId != null ? importWizardKeys.run(domain, runId) : importWizardKeys.domain(domain),
    queryFn: () => getImportWizardRun(domain, runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update, so confirming resumes polling
    // without a separate effect.
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (!status || !POLLING_STATUSES.has(status)) return false
      return isStalledRef.current ? false : POLL_INTERVAL_MS
    },
  })

  const run = runId != null ? (runQuery.data ?? null) : null

  const pollingPhase = run != null && POLLING_STATUSES.has(run.status) ? `${run.id}:${run.status}` : null
  const { isStalled: isPollingStalled, resetStall } = useStallTimeout(pollingPhase, STALL_TIMEOUT_MS)

  useEffect(() => {
    isStalledRef.current = isPollingStalled
  }, [isPollingStalled])

  const { refetch: refetchRun } = runQuery

  /** Gives the stuck phase a fresh window and re-reads the run right away. */
  const retryPolling = useCallback(() => {
    resetStall()
    void refetchRun()
  }, [resetStall, refetchRun])

  const uploadMutation = useMutation({
    mutationFn: (file: File) => analyzeImport(domain, file),
    onSuccess: (createdRun) => {
      setRunId(createdRun.id)
      setLocalStep(0)
      onRunCreated?.(createdRun.id)
    },
  })

  /**
   * Writes the run a step transition just returned into the cached detail
   * BEFORE invalidating. The PUT/POST response already carries the new
   * `status`, and the rendered step derives from the cached one: without this
   * the wizard keeps rendering the step it just left — with its submit button
   * re-enabled — until the invalidated GET lands, a window in which a second
   * submit hits the server's status guard and 422s. `ImportRunDetail` extends
   * `ImportRunSummary`, so the spread only refreshes the shared fields and
   * leaves the wizard-only ones (fields, detected columns, ...) untouched.
   */
  const applyRunTransition = useCallback(
    (updatedRun: ImportRunSummary) => {
      const key = importWizardKeys.run(domain, updatedRun.id)
      queryClient.setQueryData<ImportRunDetail>(key, (previous) =>
        previous ? { ...previous, ...updatedRun } : previous,
      )
      void queryClient.invalidateQueries({ queryKey: key })
    },
    [domain, queryClient],
  )

  /**
   * A refused transition means the client's view of the run may be stale
   * (another tab, a back-navigation, a double submit): re-read the run so the
   * wizard lands on the step the server actually is on instead of leaving the
   * operator on a dead one.
   */
  const resyncOnStaleState = useCallback(
    (error: unknown) => {
      if (runId == null || !isStaleStateError(error)) return
      void queryClient.invalidateQueries({ queryKey: importWizardKeys.run(domain, runId) })
    },
    [domain, queryClient, runId],
  )

  const configureMutation = useMutation({
    mutationFn: (payload: ConfigureImportPayload) => configureImportRun(domain, runId as number, payload),
    onSuccess: (updatedRun) => {
      setLocalStep(2)
      applyRunTransition(updatedRun)
    },
    onError: resyncOnStaleState,
  })

  const confirmMutation = useMutation({
    mutationFn: (payload: ConfirmImportPayload) => confirmImportRun(domain, runId as number, payload),
    onSuccess: applyRunTransition,
    onError: resyncOnStaleState,
  })

  const initialConfigValues = useMemo<Record<string, number | null>>(() => {
    if (!run) return {}
    const values: Record<string, number | null> = {}
    for (const field of run.global_fields) {
      values[field.id] = resolveGlobalConfigValue(run, field.id, field.default)
    }
    return values
  }, [run])

  const configValues = initialConfigValues

  const initialMapping = useMemo<Record<string, string>>(
    () => ({ ...(run?.suggested_mapping ?? {}), ...(run?.column_mapping ?? {}) }),
    [run],
  )

  const dedupStrategy = run?.dedup_strategy ?? run?.dedup_modes[0] ?? null

  const currentStep = deriveStepIndex(run?.status, localStep)

  const advanceFromUpload = useCallback(() => setLocalStep(1), [])

  const submitMapping = useCallback(
    (
      mapping: Record<string, string>,
      strategy: string,
      globalConfig: Record<string, number | null>,
      saveAsTemplate?: { name: string },
    ) => {
      configureMutation.mutate(
        { column_mapping: mapping, global_config: globalConfig, dedup_strategy: strategy },
        {
          // Side-effect POST run AFTER configure already succeeded (spec 0035
          // constraint: never blocks/rolls back the wizard's advance, which
          // `configureMutation`'s own onSuccess above already committed).
          onSuccess: () => {
            if (!saveAsTemplate || runId == null) return
            createMappingTemplate(domain, { name: saveAsTemplate.name, import_run_id: runId })
              .then(() => {
                toast.success(t('mapping.templates.saveSuccess'))
                void queryClient.invalidateQueries({ queryKey: importWizardKeys.mappingTemplates(domain) })
              })
              .catch(() => toast.error(t('mapping.templates.saveError')))
          },
        },
      )
    },
    [configureMutation, domain, runId, queryClient, t],
  )

  const advanceFromReview = useCallback(() => setLocalStep(3), [])

  const goToStep = useCallback(
    (index: number) => {
      if (!isWizardStepIndex(index)) return
      // Mapping+config is a single pre-staging step; review/summary share the
      // `reviewing` status, so only those transitions are navigable.
      if (run?.status === 'configuring' && index === 1) {
        setLocalStep(index)
      } else if (run?.status === 'reviewing' && (index === 2 || index === 3)) {
        setLocalStep(index)
      }
    },
    [run?.status],
  )

  return {
    run,
    currentStep,
    goToStep,
    isRunLoading: runId != null && runQuery.isLoading,
    isRunError: runQuery.isError,
    refetchRun: runQuery.refetch,

    isPollingStalled,
    retryPolling,

    upload: uploadMutation.mutate,
    isUploading: uploadMutation.isPending,
    uploadError: uploadMutation.isError ? resolveImportWizardErrorMessage(uploadMutation.error, t) : null,
    advanceFromUpload,

    configValues,
    mappingValues: initialMapping,
    dedupStrategy,
    submitMapping,
    isConfiguring: configureMutation.isPending,
    configureError: configureMutation.isError
      ? resolveImportWizardErrorMessage(configureMutation.error, t)
      : null,

    advanceFromReview,

    confirm: confirmMutation.mutate,
    isConfirming: confirmMutation.isPending,
    confirmError: confirmMutation.isError ? resolveImportWizardErrorMessage(confirmMutation.error, t) : null,
  }
}
