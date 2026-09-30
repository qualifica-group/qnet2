/** Measured widths the strip is laid out against, all in CSS pixels. */
export interface CategoryTabFitInput {
  /** Category ids in display order. */
  categoryIds: number[]
  /** Natural width of each category tab, parallel to `categoryIds`. */
  tabWidths: number[]
  /** Room left for the category tabs once the strip's padding and the "Tutte" tab are taken. */
  availableWidth: number
  /** Width of the "Altre" button, reserved only when some tab has to move into it. */
  moreWidth: number
  /** Flex gap between two adjacent items of the strip. */
  gap: number
  selectedCategoryId: number | null
}

/**
 * Priority+ layout of the category strip: keeps as many tabs inline as the row
 * holds, in display order, and hands the rest to the "Altre" menu. The selected
 * category never hides inside the menu — it takes the slot of the last tabs
 * that fit, so the operator always sees which category the table is scoped to.
 *
 * Returns the ids to render inline, in display order (the selected one last
 * when it was pulled forward).
 */
export function fitCategoryTabs({
  categoryIds,
  tabWidths,
  availableWidth,
  moreWidth,
  gap,
  selectedCategoryId,
}: CategoryTabFitInput): number[] {
  // Step 1: everything fits, no menu at all
  const widthOf = (count: number) =>
    tabWidths.slice(0, count).reduce((sum, width) => sum + gap + width, 0)
  if (widthOf(categoryIds.length) <= availableWidth) {
    return categoryIds
  }

  // Step 2: leading tabs that fit next to the "Altre" button
  const budget = availableWidth - gap - moreWidth
  let fitting = 0
  while (fitting < categoryIds.length && widthOf(fitting + 1) <= budget) {
    fitting += 1
  }

  // Step 3: keep the selected category inline, evicting trailing tabs to make room
  const selectedIndex = selectedCategoryId === null ? -1 : categoryIds.indexOf(selectedCategoryId)
  if (selectedIndex < fitting) {
    return categoryIds.slice(0, fitting)
  }
  const selectedWidth = gap + tabWidths[selectedIndex]
  let kept = fitting
  while (kept > 0 && widthOf(kept) + selectedWidth > budget) {
    kept -= 1
  }

  return [...categoryIds.slice(0, kept), categoryIds[selectedIndex]]
}

/** Case- and accent-insensitive form of a name, so "citta" finds "Città". */
function normalizeForSearch(value: string): string {
  return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

/** Categories whose name contains the typed term; a blank term keeps them all. */
export function filterCategoriesByName<T extends { name: string }>(categories: T[], term: string): T[] {
  const needle = normalizeForSearch(term.trim())
  if (needle === '') {
    return categories
  }

  return categories.filter((category) => normalizeForSearch(category.name).includes(needle))
}
