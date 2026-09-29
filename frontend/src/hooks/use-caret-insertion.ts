import { useEffect, useRef } from 'react'
import type { RefObject } from 'react'

interface CaretPosition {
  start: number
  end: number
}

/**
 * Tracks the caret/selection of a controlled `<input>` or `<textarea>` and
 * exposes `insertAtCaret`, which splices `token` at the LAST KNOWN caret
 * position — not at the end of the value. Selection tracking lives in a ref,
 * not state: caret movement alone must never trigger a re-render. After an
 * insertion, a pending caret is restored on the DOM node once React has
 * committed the new value — `useEffect` with no dependency array runs after
 * every render, the only way to react to "the value this ref points at just
 * changed" without lifting the value into a second, duplicated piece of
 * state.
 *
 * Generic over `HTMLInputElement | HTMLTextAreaElement`: both natively share
 * `selectionStart`/`selectionEnd`/`setSelectionRange`. Shared by
 * `email-templates`'s subject `<input>` (spec 0175 AC-022) and
 * `document-layouts`'s run `<textarea>` (spec 0069 AC-122, migrated here from
 * that module's own former copy — no dead code left behind, engineering.md
 * §1.4).
 */
export function useCaretInsertion<TElement extends HTMLInputElement | HTMLTextAreaElement>(
  elementRef: RefObject<TElement | null>,
  value: string,
  onChange: (next: string) => void,
) {
  const caretRef = useRef<CaretPosition>({ start: value.length, end: value.length })
  const pendingRef = useRef<CaretPosition | null>(null)

  useEffect(() => {
    if (!pendingRef.current) {
      return
    }
    const element = elementRef.current
    if (element) {
      element.focus()
      element.setSelectionRange(pendingRef.current.start, pendingRef.current.end)
    }
    pendingRef.current = null
  })

  function trackSelection(event: { currentTarget: TElement }) {
    caretRef.current = {
      start: event.currentTarget.selectionStart ?? value.length,
      end: event.currentTarget.selectionEnd ?? value.length,
    }
  }

  function insertAtCaret(token: string) {
    const { start, end } = caretRef.current
    const nextValue = value.slice(0, start) + token + value.slice(end)
    const caret = start + token.length
    pendingRef.current = { start: caret, end: caret }
    caretRef.current = { start: caret, end: caret }
    onChange(nextValue)
  }

  return { trackSelection, insertAtCaret }
}
