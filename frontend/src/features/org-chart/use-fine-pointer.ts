import { useSyncExternalStore } from 'react'

const FINE_POINTER_QUERY = '(pointer: fine)'

function subscribe(onChange: () => void): () => void {
  if (typeof window.matchMedia !== 'function') return () => {}
  const query = window.matchMedia(FINE_POINTER_QUERY)
  query.addEventListener('change', onChange)
  return () => query.removeEventListener('change', onChange)
}

function getSnapshot(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia(FINE_POINTER_QUERY).matches
}

/** True on mouse/trackpad devices: drag and drop is desktop-only, touch keeps "Sposta in…". */
export function useFinePointer(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, () => false)
}
