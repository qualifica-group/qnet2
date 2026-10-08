import { useEffect, useRef } from 'react'

/**
 * A confirmation (`useConfirm`, an alert dialog mounted at the app root, far
 * from the row in the React tree) is answered on top of the open row, often
 * because its own editor asked for it (e.g. "replace the roles with the
 * anagrafica's?"): answering it is never a press outside.
 */
const CONFIRMATION_SELECTOR = '[role="alertdialog"]'

/**
 * Calls `onDismiss` when a pointer goes down outside the element that spreads
 * the returned handler, while `active`. "Inside" follows the REACT tree, not
 * the DOM: a picker's popover or a quick-create dialog portalled to `body`
 * still bubbles its React events through the row that rendered it, so using
 * it never dismisses the row.
 *
 * A press inside a confirmation dialog is not outside either (see
 * `CONFIRMATION_SELECTOR`).
 *
 * Why it works: React dispatches from its listeners on the root and portal
 * containers, both below `document`, so the row's capture handler marks the
 * press as inside before the document listener below sees it.
 */
export function useOutsidePointerDismiss(active: boolean, onDismiss: () => void) {
  const pressedInsideRef = useRef(false)
  const onDismissRef = useRef(onDismiss)

  // Latest callback without re-subscribing the document listener on every render.
  useEffect(() => {
    onDismissRef.current = onDismiss
  })

  useEffect(() => {
    if (!active) {
      return
    }
    const handlePointerDown = (event: PointerEvent) => {
      if (pressedInsideRef.current) {
        pressedInsideRef.current = false
        return
      }
      if (event.target instanceof Element && event.target.closest(CONFIRMATION_SELECTOR)) {
        return
      }
      onDismissRef.current()
    }
    document.addEventListener('pointerdown', handlePointerDown)
    return () => document.removeEventListener('pointerdown', handlePointerDown)
  }, [active])

  return {
    onPointerDownCapture: () => {
      pressedInsideRef.current = true
    },
  }
}
