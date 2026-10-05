import { useLayoutEffect, useState, type RefObject } from 'react'
import { fitCategoryTabs } from '@/features/request-management/category-tab-fit'

/** Only the id is measured: any strip of numerically keyed tabs can use the fit (the statistics tabs too, spec 0192). */
type MeasuredCategory = { id: number }

/**
 * `data-tab-measure` values in the measuring layer: the "Tutte" tab, the menu
 * button labelled "Altre (N)", and the same button in its compact form (star
 * only), which stays on the strip even when every tab fits (spec 0184 AC-011).
 */
export const MEASURE_ALL = 'all'
export const MEASURE_MORE = 'more'
export const MEASURE_MANAGE = 'manage'

interface StripMeasurement {
  availableWidth: number
  moreWidth: number
  gap: number
  tabWidths: Map<number, number>
}

/**
 * Lays the category strip out against the width it really has (priority+):
 * reads the natural width of every category's tab off the hidden measuring
 * layer and the room in the visible trough, then returns which of `stripIds`
 * (the strip's candidates, in order) stay inline — the rest go to the "Altre"
 * menu. Only the measurement lives in state, keyed on the stable query data;
 * the fit is recomputed on every render, so picking a category or a favourite
 * never waits on a resize nor triggers a new measurement.
 *
 * Until the first measurement every candidate is returned: the strip's
 * wrapper clips, so that frame can never widen the page.
 */
export function useCategoryTabFit(
  troughRef: RefObject<HTMLElement | null>,
  measureRef: RefObject<HTMLElement | null>,
  categories: MeasuredCategory[],
  stripIds: number[],
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

  if (measurement === null) {
    return stripIds
  }

  return fitCategoryTabs({
    categoryIds: stripIds,
    tabWidths: stripIds.map((id) => measurement.tabWidths.get(id) ?? 0),
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
  categories: MeasuredCategory[],
): StripMeasurement {
  const style = getComputedStyle(trough)
  const paddingX = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0)
  // A trough wider than the screen (an oversized sibling, a content-sized
  // ancestor) must still fold its tabs at the screen edge. `clientWidth`
  // excludes the scrollbar; the fallback covers environments with no layout.
  const viewportWidth = document.documentElement.clientWidth || window.innerWidth
  const troughWidth = Math.min(trough.clientWidth, viewportWidth - trough.getBoundingClientRect().left)

  const gap = parseFloat(style.columnGap) || 0
  const manageWidth = widthOf(layer, MEASURE_MANAGE)

  // The compact menu button is always there, so its room is taken up front;
  // folding tabs only costs the extra width of its "Altre (N)" label.
  return {
    availableWidth: troughWidth - paddingX - widthOf(layer, MEASURE_ALL) - gap - manageWidth,
    moreWidth: Math.max(0, widthOf(layer, MEASURE_MORE) - manageWidth),
    gap,
    tabWidths: new Map(categories.map((category) => [category.id, widthOf(layer, String(category.id))])),
  }
}
