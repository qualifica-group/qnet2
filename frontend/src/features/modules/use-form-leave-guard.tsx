import { useCallback, useContext, useEffect, useRef, type ReactNode } from 'react'
import { UNSAFE_DataRouterContext } from 'react-router-dom'
import { useOptionalConfirm, type ConfirmOptions } from '@/components/confirm-dialog-context'
import { NavigationLeaveBlocker } from '@/features/modules/navigation-leave-blocker'
import { useSheetCloseGuard } from '@/features/modules/sheet-close-guard'

export interface FormLeaveGuard {
  /** Asks to leave (once: a confirmed leave is not asked again); `true` = the caller may leave. */
  confirmLeave: () => Promise<boolean>
  /** Lets the next exit through unasked — call it right before leaving after a successful save. */
  allowLeave: () => void
  /** Render it once inside the form screen: it blocks in-app navigation (only under the app's data router). */
  navigationGuard: ReactNode
}

/**
 * Confirms before a form being filled is left by ANY way out (spec 0195,
 * user directive 2026-10-06 for the task create form): its own Cancel (the
 * caller awaits `confirmLeave`), the module Sheet's X/overlay/Esc
 * (`useSheetCloseGuard`), an in-app navigation (`useBlocker`) and a tab
 * close/reload (`beforeunload`, the browser's own prompt).
 *
 * `useBlocker` only exists under a data router: the app's
 * `createBrowserRouter` is one, a test's `MemoryRouter` is not — hence the
 * blocker is a child mounted only when the data router context is there.
 * Without a confirm provider (isolated tests) leaving is simply allowed.
 */
export function useFormLeaveGuard(options: ConfirmOptions): FormLeaveGuard {
  const confirm = useOptionalConfirm()
  const isDataRouter = useContext(UNSAFE_DataRouterContext) !== null
  const allowLeaveRef = useRef(false)
  const { title, description, confirmLabel, cancelLabel, tone } = options

  const confirmLeave = useCallback(async () => {
    if (allowLeaveRef.current || !confirm) {
      return true
    }
    const confirmed = await confirm({ title, description, confirmLabel, cancelLabel, tone })
    allowLeaveRef.current = confirmed
    return confirmed
  }, [confirm, title, description, confirmLabel, cancelLabel, tone])

  const allowLeave = useCallback(() => {
    allowLeaveRef.current = true
  }, [])

  const shouldBlock = useCallback(() => !allowLeaveRef.current, [])

  // The browser shows its own generic prompt: the message cannot be customized.
  useEffect(() => {
    const handleBeforeUnload = (event: BeforeUnloadEvent) => {
      if (!allowLeaveRef.current) {
        event.preventDefault()
      }
    }
    window.addEventListener('beforeunload', handleBeforeUnload)
    return () => window.removeEventListener('beforeunload', handleBeforeUnload)
  }, [])

  useSheetCloseGuard(confirmLeave)

  return {
    confirmLeave,
    allowLeave,
    navigationGuard: isDataRouter ? <NavigationLeaveBlocker shouldBlock={shouldBlock} confirmLeave={confirmLeave} /> : null,
  }
}
