/** Measured widths the strip is laid out against, all in CSS pixels. */
export interface CategoryTabFitInput {
  /** Category ids in display order. */
  categoryIds: number[]
  /** Natural width of each category tab, parallel to `categoryIds`. */
  tabWidths: number[]
  /** Room left for the category tabs once the strip's padding, "Tutte" and the compact menu button are taken. */
  availableWidth: number
  /** Extra width the menu button takes as "Altre (N)", reserved only when some tab has to move into it. */
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

  // Step 2: leading tabs that fit next to the "Altre (N)" button
  const budget = availableWidth - moreWidth
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

interface CategoryArrangementInput<T extends { id: number }> {
  /** Categories in the server's order (by name). */
  categories: T[]
  favoriteCategoryIds: number[]
  showOnlyFavorites: boolean
  selectedCategoryId: number | null
}

/**
 * Applies the actor's favourites to the strip (spec 0184): the candidates for
 * the inline slots, favourites first with each group keeping the server's
 * order — or, with "only favourites" on, just the favourites plus the selected
 * category, so the active scope never disappears. With no favourite among the
 * categories the switch has nothing to narrow to and every category competes.
 */
export function arrangeCategoryTabs<T extends { id: number }>({
  categories,
  favoriteCategoryIds,
  showOnlyFavorites,
  selectedCategoryId,
}: CategoryArrangementInput<T>): T[] {
  const favoriteIds = new Set(favoriteCategoryIds)
  const favorites = categories.filter((category) => favoriteIds.has(category.id))

  if (!showOnlyFavorites || favorites.length === 0) {
    return [...favorites, ...categories.filter((category) => !favoriteIds.has(category.id))]
  }
  const selected = categories.find((category) => category.id === selectedCategoryId)

  return selected && !favoriteIds.has(selected.id) ? [...favorites, selected] : favorites
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
