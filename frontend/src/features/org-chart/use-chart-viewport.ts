import { useRef, useState, type PointerEvent } from 'react'

export const MIN_ZOOM = 0.3
export const MAX_ZOOM = 1.5
const ZOOM_STEP = 0.1
const DEFAULT_ZOOM = 1
/** Pointer presses on these elements belong to them (drag, menus, links), never to panning. */
const INTERACTIVE_SELECTOR = '[role="treeitem"], button, a'

interface PanOrigin {
  pointerX: number
  pointerY: number
  scrollLeft: number
  scrollTop: number
}

export function clampZoom(value: number): number {
  return Math.round(Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value)) * 100) / 100
}

/**
 * Zoom and pan of the chart viewport (spec 0274 D-5). Zoom scales the canvas,
 * the scroll area follows the scaled size, so scrollbars and dragging the
 * background (which just moves the scroll position) both pan the chart.
 */
export function useChartViewport(contentWidth: number, contentHeight: number) {
  const viewportRef = useRef<HTMLDivElement>(null)
  const panOrigin = useRef<PanOrigin | null>(null)
  const [zoom, setZoom] = useState(DEFAULT_ZOOM)

  function fit(): void {
    const viewport = viewportRef.current
    if (!viewport || viewport.clientWidth === 0 || contentWidth === 0 || contentHeight === 0) return
    setZoom(clampZoom(Math.min(DEFAULT_ZOOM, viewport.clientWidth / contentWidth, viewport.clientHeight / contentHeight)))
  }

  function onPointerDown(event: PointerEvent<HTMLDivElement>): void {
    const viewport = viewportRef.current
    // React events bubble from portals (menus, hover cards): only presses inside the viewport DOM can pan.
    if (!viewport || event.button !== 0 || !viewport.contains(event.target as Node)) return
    if ((event.target as Element).closest(INTERACTIVE_SELECTOR)) return
    panOrigin.current = {
      pointerX: event.clientX,
      pointerY: event.clientY,
      scrollLeft: viewport.scrollLeft,
      scrollTop: viewport.scrollTop,
    }
    viewport.setPointerCapture?.(event.pointerId)
  }

  function onPointerMove(event: PointerEvent<HTMLDivElement>): void {
    const viewport = viewportRef.current
    const origin = panOrigin.current
    if (!viewport || !origin) return
    viewport.scrollLeft = origin.scrollLeft - (event.clientX - origin.pointerX)
    viewport.scrollTop = origin.scrollTop - (event.clientY - origin.pointerY)
  }

  function onPointerUp(): void {
    panOrigin.current = null
  }

  return {
    viewportRef,
    zoom,
    zoomIn: () => setZoom((value) => clampZoom(value + ZOOM_STEP)),
    zoomOut: () => setZoom((value) => clampZoom(value - ZOOM_STEP)),
    fit,
    panHandlers: { onPointerDown, onPointerMove, onPointerUp, onPointerCancel: onPointerUp },
  }
}
