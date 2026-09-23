import type { MouseEvent } from 'react'

/** Controls that keep their own click inside a task card/row (drag handle, checkbox, expand, people, links). */
const INTERACTIVE_SELECTOR = 'button, a, input, select, textarea, [role="checkbox"], [role="button"]'

/**
 * Click handler for a whole task card/row: opens the task unless the click
 * landed on one of its controls. The `contains` check drops clicks that only
 * bubble here through a React portal (e.g. the people hover card content).
 */
export function openTaskOnCardClick(event: MouseEvent<HTMLElement>, onOpen: () => void) {
  const target = event.target
  if (!(target instanceof Element) || !event.currentTarget.contains(target)) {
    return
  }
  if (target.closest(INTERACTIVE_SELECTOR)) {
    return
  }
  onOpen()
}
