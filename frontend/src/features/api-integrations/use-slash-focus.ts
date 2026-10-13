import { useEffect, type RefObject } from 'react'

const EDITABLE_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT'])

/** Focuses `ref` when "/" is pressed outside any editable field (a browser-wide shortcut, hence the listener). */
export function useSlashFocus(ref: RefObject<HTMLInputElement | null>) {
  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      const target = event.target instanceof HTMLElement ? event.target : null
      const isEditing = target !== null && (EDITABLE_TAGS.has(target.tagName) || target.isContentEditable)
      if (event.key !== '/' || isEditing || event.metaKey || event.ctrlKey || event.altKey) {
        return
      }
      event.preventDefault()
      ref.current?.focus()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [ref])
}
