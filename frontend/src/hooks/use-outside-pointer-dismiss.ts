import { useEffect, useRef } from 'react'

/**
 * Calls `onDismiss` when a pointer goes down outside the element that spreads
 * the returned handler, while `active`. "Inside" follows the REACT tree, not
 * the DOM: a picker's popover or a quick-create dialog portalled to `body`
 * still bubbles its React events through the row that rendered it, so using
 * it never dismisses the row.
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
    const handlePointerDown = () => {
      if (pressedInsideRef.current) {
        pressedInsideRef.current = false
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
