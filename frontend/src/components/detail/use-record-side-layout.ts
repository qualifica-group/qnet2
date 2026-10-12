import { useLayoutEffect, useState, type RefObject } from 'react'
import { RECORD_SIDE_LAYOUT_MIN_WIDTH_PX } from '@/components/detail/record-layout'

/** `side`: record + side column; `tabs`: the side column's tabs join the record's own tab strip. */
export type RecordSideLayout = 'side' | 'tabs'

const COLLAPSED_STORAGE_KEY = 'record.side.collapsed'

/**
 * Chooses the layout from the body's REAL width (page, modal Sheet, any
 * sidebar state), which a container query cannot do here: the two layouts are
 * different trees, not different classes. Measured before paint, so no frame
 * shows the wrong one. A zero width (not laid out yet, jsdom) keeps `side`.
 */
export function useRecordSideLayout(ref: RefObject<HTMLElement | null>): RecordSideLayout {
  const [width, setWidth] = useState(0)

  useLayoutEffect(() => {
    const element = ref.current
    if (!element) return

    const measure = () => setWidth(element.getBoundingClientRect().width)

    measure()
    const observer = new ResizeObserver(measure)
    observer.observe(element)

    return () => observer.disconnect()
  }, [ref])

  return width > 0 && width < RECORD_SIDE_LAYOUT_MIN_WIDTH_PX ? 'tabs' : 'side'
}

/** Until the user opens it once, the side column starts collapsed (user directive 2026-10-12). */
const DEFAULT_COLLAPSED = true

function readStoredCollapsed(): boolean {
  try {
    const stored = window.localStorage.getItem(COLLAPSED_STORAGE_KEY)
    return stored === null ? DEFAULT_COLLAPSED : stored === 'true'
  } catch {
    return DEFAULT_COLLAPSED
  }
}

/**
 * Collapsed/expanded side column: ONE preference per browser shared by every
 * record detail, so opening or closing it on one page carries over to all.
 */
export function useRecordSideCollapsed() {
  const [collapsed, setCollapsedState] = useState(readStoredCollapsed)

  const setCollapsed = (next: boolean) => {
    setCollapsedState(next)
    try {
      window.localStorage.setItem(COLLAPSED_STORAGE_KEY, String(next))
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { collapsed, setCollapsed }
}
