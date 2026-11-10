import type { MouseEvent } from 'react'

/** A click the browser should handle itself: new tab/window, download, non-primary button. */
export function isModifiedClick(event: MouseEvent): boolean {
  return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
}
