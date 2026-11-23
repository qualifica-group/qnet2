import { useSyncExternalStore } from 'react'

/** Tailwind `md`: below it the page is touch-first and the block handle only opens its menu. */
const DESKTOP_QUERY = '(min-width: 768px)'

function subscribe(onChange: () => void): () => void {
  if (typeof window.matchMedia !== 'function') {
    return () => {}
  }
  const query = window.matchMedia(DESKTOP_QUERY)
  query.addEventListener('change', onChange)
  return () => query.removeEventListener('change', onChange)
}

function getSnapshot(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia(DESKTOP_QUERY).matches
}

/** True from `md` up: the block handle can be dragged. */
export function useCanDrag(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, () => false)
}
