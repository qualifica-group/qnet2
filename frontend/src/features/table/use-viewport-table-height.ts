import { useCallback, useLayoutEffect, useRef, useState } from 'react'

/**
 * Breathing room kept below the grid so the card's bottom edge and the page
 * padding stay visible instead of the table running into the viewport edge.
 */
const VIEWPORT_BOTTOM_GAP = 24

/**
 * Absolute floor: on a very short viewport the grid keeps a usable page of rows
 * and scrolls internally, as it did at the former fixed height.
 */
export const MIN_TABLE_HEIGHT = 320

/**
 * The floor follows the screen too: a grid sitting far down a long page (an
 * embedded panel on a detail page) has little of the first screenful left to
 * fill, and would otherwise collapse to the absolute floor on any monitor.
 */
const MIN_VIEWPORT_RATIO = 0.5

interface UseViewportTableHeightOptions {
  /** Off in fullscreen, where a flex parent owns the height. */
  enabled: boolean
  /** Ceiling: the height the current page of rows actually needs. */
  maxHeight: number
}

/**
 * Sizes a grid container to the screen: it fills what is left of the first
 * screenful below whatever chrome the module renders above it (page header,
 * stats, tabs, toolbar, filter panel), floored at half the viewport and capped
 * at the height the page of rows needs.
 *
 * Attach `containerRef` to the element the returned `height` is applied to.
 */
export function useViewportTableHeight({
  enabled,
  maxHeight,
}: UseViewportTableHeightOptions) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [height, setHeight] = useState<number | null>(null)

  const measure = useCallback(() => {
    const node = containerRef.current
    if (!node) {
      return
    }
    // Offset from the top of the DOCUMENT, not of the viewport: an offset read
    // while the page is scrolled would grow the grid, which lengthens the page,
    // which allows more scroll — a feedback loop. Measured this way the result
    // is the same at any scroll position.
    const documentTop = node.getBoundingClientRect().top + window.scrollY
    const available = window.innerHeight - documentTop - VIEWPORT_BOTTOM_GAP
    const floor = Math.max(MIN_TABLE_HEIGHT, window.innerHeight * MIN_VIEWPORT_RATIO)

    // The content ceiling wins over the floor: a 5-row page never draws an
    // empty half-screen of grid.
    setHeight(Math.round(Math.min(Math.max(available, floor), maxHeight)))
  }, [maxHeight])

  useLayoutEffect(() => {
    if (!enabled) {
      return
    }
    // Before paint, so the grid never flashes at a wrong height.
    measure()
    window.addEventListener('resize', measure)
    // The offset also moves when the chrome above the grid changes height (the
    // advanced-filters panel opening, a toolbar wrapping, a stats block
    // loading) — none of which emits a resize event.
    const observer =
      typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(() => measure())
    observer?.observe(document.body)

    return () => {
      window.removeEventListener('resize', measure)
      observer?.disconnect()
    }
  }, [enabled, measure])

  // Derived, not reset through the effect: disabled, the caller must fall back
  // to its flex parent even before the effect for that render has run.
  return { containerRef, height: enabled ? height : null }
}
