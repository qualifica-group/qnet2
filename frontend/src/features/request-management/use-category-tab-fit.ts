import { useLayoutEffect, useState, type RefObject } from 'react'
import { fitCategoryTabs } from '@/features/request-management/category-tab-fit'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/** `data-tab-measure` value of the "Tutte" tab and of the "Altre" button in the measuring layer. */
export const MEASURE_ALL = 'all'
export const MEASURE_MORE = 'more'

interface StripMeasurement {
  availableWidth: number
  moreWidth: number
  gap: number
  tabWidths: Map<number, number>
}

/**
 * Lays the category strip out against the width it really has (priority+):
 * reads each tab's natural width off the hidden measuring layer, the room in
 * the visible trough, and returns which categories stay inline — the rest go
 * to the "Altre" menu. Only the measurement lives in state; the fit itself is
 * recomputed on every render, so picking a category never waits on a resize.
 *
 * Until the first measurement every category is returned: the trough clips,
 * so that frame can never widen the page.
 */
export function useCategoryTabFit(
  troughRef: RefObject<HTMLElement | null>,
  measureRef: RefObject<HTMLElement | null>,
  categories: RequestManagementProductCategory[],
  selectedCategoryId: number | null,
): number[] {
  const [measurement, setMeasurement] = useState<StripMeasurement | null>(null)

  useLayoutEffect(() => {
    const trough = troughRef.current
    const layer = measureRef.current
    if (!trough || !layer) return

    const measure = () => setMeasurement(measureStrip(trough, layer, categories))

    measure()
    const observer = new ResizeObserver(measure)
    observer.observe(trough)
    observer.observe(layer)
    // A viewport resize can shrink the room without resizing a content-sized ancestor.
    window.addEventListener('resize', measure)

    return () => {
      observer.disconnect()
      window.removeEventListener('resize', measure)
    }
  }, [troughRef, measureRef, categories])

  const categoryIds = categories.map((category) => category.id)
  if (measurement === null) {
    return categoryIds
  }

  return fitCategoryTabs({
    categoryIds,
    tabWidths: categoryIds.map((id) => measurement.tabWidths.get(id) ?? 0),
    availableWidth: measurement.availableWidth,
    moreWidth: measurement.moreWidth,
    gap: measurement.gap,
    selectedCategoryId,
  })
}

function widthOf(layer: HTMLElement, key: string): number {
  return layer.querySelector<HTMLElement>(`[data-tab-measure="${key}"]`)?.offsetWidth ?? 0
}

function measureStrip(
  trough: HTMLElement,
  layer: HTMLElement,
  categories: RequestManagementProductCategory[],
): StripMeasurement {
  const style = getComputedStyle(trough)
  const paddingX = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0)
  // A trough wider than the screen (an oversized sibling, a content-sized
  // ancestor) must still fold its tabs at the screen edge. `clientWidth`
  // excludes the scrollbar; the fallback covers environments with no layout.
  const viewportWidth = document.documentElement.clientWidth || window.innerWidth
  const troughWidth = Math.min(trough.clientWidth, viewportWidth - trough.getBoundingClientRect().left)

  return {
    availableWidth: troughWidth - paddingX - widthOf(layer, MEASURE_ALL),
    moreWidth: widthOf(layer, MEASURE_MORE),
    gap: parseFloat(style.columnGap) || 0,
    tabWidths: new Map(categories.map((category) => [category.id, widthOf(layer, String(category.id))])),
  }
}
