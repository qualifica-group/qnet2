import { useLayoutEffect, useState, type RefObject } from 'react'
import type { PreviewMeasurement } from '@/features/document-layouts/editor/preview/preview-pagination'

/** Marks the body area of a sheet and each body block's wrapper, so the measurement reads the sheets actually drawn. */
export const PREVIEW_BODY_ATTRIBUTE = 'data-preview-body'
export const PREVIEW_BLOCK_ATTRIBUTE = 'data-preview-block'

function readMeasurement(container: HTMLElement): PreviewMeasurement | null {
  const body = container.querySelector<HTMLElement>(`[${PREVIEW_BODY_ATTRIBUTE}]`)
  if (!body) return null

  const blocks = container.querySelectorAll<HTMLElement>(`[${PREVIEW_BLOCK_ATTRIBUTE}]`)
  return {
    bodyHeight: body.clientHeight,
    blockHeights: Array.from(blocks, (block) => block.offsetHeight),
    gap: Number.parseFloat(getComputedStyle(body).rowGap) || 0,
  }
}

function sameMeasurement(a: PreviewMeasurement | null, b: PreviewMeasurement | null): boolean {
  if (a === null || b === null) return a === b
  return (
    a.bodyHeight === b.bodyHeight &&
    a.gap === b.gap &&
    a.blockHeights.length === b.blockHeights.length &&
    a.blockHeights.every((height, index) => height === b.blockHeights[index])
  )
}

/**
 * Reads the body room of the first sheet and every body block's height off
 * the sheets drawn in `containerRef`, after each render and whenever a block
 * resizes (an image decoding late). A block keeps its height on whatever
 * sheet it lands (same width), so re-paginating settles in one pass; an
 * unchanged reading keeps the previous state, so it never loops.
 */
export function usePreviewMeasurement(containerRef: RefObject<HTMLElement | null>): PreviewMeasurement | null {
  const [measurement, setMeasurement] = useState<PreviewMeasurement | null>(null)

  useLayoutEffect(() => {
    const container = containerRef.current
    if (!container) return

    const measure = () => {
      const next = readMeasurement(container)
      setMeasurement((previous) => (sameMeasurement(previous, next) ? previous : next))
    }

    measure()
    const observer = new ResizeObserver(measure)
    container.querySelectorAll(`[${PREVIEW_BLOCK_ATTRIBUTE}]`).forEach((block) => observer.observe(block))

    return () => observer.disconnect()
  })

  return measurement
}
