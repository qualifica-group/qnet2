import { useEffect, useRef } from 'react'
import type { RefObject } from 'react'

interface CaretPosition {
  start: number
  end: number
}

/**
 * Tracks the caret/selection of a controlled `<textarea>` and exposes
 * `insertAtCaret`, which splices `token` at the LAST KNOWN caret position —
 * not at the end of the value (AC-122: "il click ... inserisce il token nel
 * punto di inserimento del run attivo, non a fine testo, se il cursore e
 * altrove"). Selection tracking lives in a ref, not state: caret movement
 * alone must never trigger a re-render. After an insertion, a pending caret
 * is restored on the DOM node once React has committed the new value —
 * `useEffect` with no dependency array runs after every render, the only way
 * to react to "the value this ref points at just changed" without lifting
 * the textarea's value into a second, duplicated piece of state.
 */
export function useCaretInsertion(
  elementRef: RefObject<HTMLTextAreaElement | null>,
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

  function trackSelection(event: { currentTarget: HTMLTextAreaElement }) {
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
