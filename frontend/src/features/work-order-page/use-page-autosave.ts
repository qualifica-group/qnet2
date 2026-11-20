import { useCallback, useEffect, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { isPageClosedError, pageConflictPayload, isPageConflict, saveWorkOrderPage } from '@/features/work-order-page/api'
import { workOrderPageKeys } from '@/features/work-order-page/query-keys'
import type { SavePagePayload, WorkOrderPagePayload } from '@/features/work-order-page/types'

/** Quiet time after the last keystroke before the page is saved (spec 0380 AC-022). */
export const AUTOSAVE_DEBOUNCE_MS = 1500

export type PageSaveStatus = 'idle' | 'saving' | 'saved' | 'error' | 'conflict'

interface UsePageAutosaveArgs {
  workOrderId: number
  /** `version` of the page the editor was seeded from. */
  initialVersion: number
  /** Puts server content back into the editor (conflict "Ricarica"). */
  applyServerContent: (content: string | null) => void
}

/**
 * Debounced optimistic-concurrency saving (spec 0380 D-3). Orchestration:
 * schedule() records the latest content and (re)starts the debounce; run()
 * sends it unless a request is already in flight (one at a time, the newest
 * content wins) and chains a follow-up save when more text arrived meanwhile.
 * A version conflict suspends saving until reload(); a "work order closed"
 * 409 makes the page read-only; any other failure keeps the text and offers retry().
 */
export function usePageAutosave({ workOrderId, initialVersion, applyServerContent }: UsePageAutosaveArgs) {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<PageSaveStatus>('idle')
  const [conflict, setConflict] = useState<WorkOrderPagePayload | null>(null)
  const [isClosed, setIsClosed] = useState(false)

  const versionRef = useRef(initialVersion)
  const pendingRef = useRef<{ content: string | null } | null>(null)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const inFlightRef = useRef<Promise<void> | null>(null)
  const suspendedRef = useRef(false)
  const applyRef = useRef(applyServerContent)
  useEffect(() => {
    applyRef.current = applyServerContent
  }, [applyServerContent])

  const mutation = useMutation<WorkOrderPagePayload, unknown, SavePagePayload>({
    mutationFn: (payload) => saveWorkOrderPage(workOrderId, payload),
  })
  const { mutateAsync } = mutation

  const run = useCallback((): Promise<void> => {
    // Step 1: nothing to send, a request already running, or saving suspended
    const pending = pendingRef.current
    if (!pending || inFlightRef.current || suspendedRef.current) {
      return inFlightRef.current ?? Promise.resolve()
    }
    // Step 2: take the newest content and send it with the version we hold
    pendingRef.current = null
    setStatus('saving')
    const task = (async () => {
      let current: { content: string | null } | null = pending
      let failed = false
      while (current) {
        try {
          const saved = await mutateAsync({ content: current.content, version: versionRef.current })
          versionRef.current = saved.version
          queryClient.setQueryData(workOrderPageKeys.page(workOrderId), saved)
          // Step 3: more text arrived while saving -> save that too, else we are done
          current = pendingRef.current
          pendingRef.current = null
        } catch (error) {
          failed = true
          if (isPageConflict(error)) {
            suspendedRef.current = true
            setConflict(pageConflictPayload(error))
            setStatus('conflict')
          } else if (isPageClosedError(error)) {
            suspendedRef.current = true
            setIsClosed(true)
            setStatus('idle')
          } else {
            // Keep the text: unless newer text already replaced it, put it back for retry().
            pendingRef.current ??= current
            setStatus('error')
          }
          current = null
        }
      }
      inFlightRef.current = null
      if (!failed) {
        setStatus('saved')
      }
    })()
    inFlightRef.current = task
    return task
  }, [mutateAsync, queryClient, workOrderId])

  const schedule = useCallback(
    (content: string | null) => {
      pendingRef.current = { content }
      if (suspendedRef.current) {
        return
      }
      if (timerRef.current) {
        clearTimeout(timerRef.current)
      }
      timerRef.current = setTimeout(() => {
        timerRef.current = null
        void run()
      }, AUTOSAVE_DEBOUNCE_MS)
    },
    [run],
  )

  /**
   * Saves right away and resolves once everything pending is on the server.
   * It first waits for a request already in flight: a poll/task inserted after
   * that save must not be dropped by it (the server prunes polls the saved
   * content does not cite).
   */
  const flush = useCallback(async () => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
    while (inFlightRef.current) {
      await inFlightRef.current
    }
    await run()
  }, [run])

  /** Conflict: drop local edits, show the server version and resume saving from its version. */
  const reload = useCallback(() => {
    if (!conflict) {
      return
    }
    pendingRef.current = null
    versionRef.current = conflict.version
    suspendedRef.current = false
    applyRef.current(conflict.content)
    queryClient.setQueryData(workOrderPageKeys.page(workOrderId), conflict)
    setConflict(null)
    setStatus('idle')
  }, [conflict, queryClient, workOrderId])

  // Do not lose typed text when the panel unmounts inside the debounce window.
  useEffect(
    () => () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current)
        timerRef.current = null
        void run()
      }
    },
    [run],
  )

  return { status, conflict, isClosed, schedule, flush, retry: flush, reload }
}
