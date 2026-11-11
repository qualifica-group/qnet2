import { useMemo } from 'react'
import { arrangeCategoryTabs } from '@/features/category-tabs/category-tab-fit'
import { useCategoryTabLevel } from '@/features/category-tabs/category-tab-level'
import { useCategoryTabPreferences } from '@/features/category-tabs/use-category-tab-preferences'
import type { RequestDashboardCategory } from '@/features/request-management/dashboard-api'
import type { RequestReportCategory } from '@/features/request-management/report-api'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Roots the statistics strip's query keys and browser storage. Its favourites
 * live under the statistics permission (`{module}/report/category-tab-preferences`),
 * not Gestione Richieste's own, which a statistics-only user cannot reach.
 */
const STATISTICS_STRIP_KEY = 'request-statistics'

/**
 * One strip tab. The fit and the menu key tabs by number: here that number is
 * the category's POSITION, so the layout never depends on what a branch key
 * looks like; the favourites translate it to the category id the server keeps.
 */
export interface StripCategory {
  id: number
  key: string
  name: string
}

interface DashboardCategoryStripInput {
  categories: RequestDashboardCategory[]
  /** The report branches, for each category's depth: a missing one counts as a main category. */
  branches: RequestReportCategory[]
  /** The active tab: the overview or a category key. */
  value: string
  onSelect: (tab: string) => void
}

/**
 * Everything the statistics strip needs besides its layout (spec 0385): the
 * categories of the chosen level (every one, or only the main ones), the
 * actor's favourites arranged first or alone, and the menu's handlers.
 * The level is a browser preference, every category by default; the open
 * category stays on the strip even when the level or "only favourites" would
 * hide it. Bulk actions touch only the categories the menu lists, so a
 * favourite outside the current selection survives "deselect all".
 */
export function useDashboardCategoryStrip({ categories, branches, value, onSelect }: DashboardCategoryStripInput) {
  const module = useRequestModule()
  const { level, setLevel } = useCategoryTabLevel(STATISTICS_STRIP_KEY, 'all')
  const { preferences, toggleFavorite, setFavorites, setShowOnlyFavorites } = useCategoryTabPreferences({
    key: STATISTICS_STRIP_KEY,
    apiBasePath: `${module.apiBasePath}/report`,
  })

  // Step 1: every returned category, numbered by position. Memoized on the
  // query data: the fit re-measures whenever this identity changes.
  const stripCategories = useMemo<StripCategory[]>(
    () => categories.map((category, index) => ({ id: index, key: category.key, name: category.label })),
    [categories],
  )
  const selectedIndex = stripCategories.findIndex((category) => category.key === value)
  const selectedId = selectedIndex === -1 ? null : selectedIndex
  const categoryIdOf = (position: number) => Number(stripCategories[position].key)

  // Step 2: the chosen level.
  const subcategoryKeys = new Set(branches.filter((branch) => branch.depth > 0).map((branch) => branch.key))
  const levelCategories =
    level === 'root' ? stripCategories.filter((category) => !subcategoryKeys.has(category.key)) : stripCategories

  // Step 3: the favourites, from category ids to positions and back.
  const favoriteIds = preferences.favorite_category_ids
  const favoritePositions = stripCategories
    .filter((category) => favoriteIds.includes(Number(category.key)))
    .map((category) => category.id)
  const listedIds = new Set(levelCategories.map((category) => Number(category.key)))
  const setListedFavorites = (positions: number[]) =>
    setFavorites([...favoriteIds.filter((id) => !listedIds.has(id)), ...positions.map(categoryIdOf)])

  // Step 4: the inline candidates, favourites first, the open category always among them.
  const arrangedIds = arrangeCategoryTabs({
    categories: levelCategories,
    favoriteCategoryIds: favoritePositions,
    showOnlyFavorites: preferences.show_only_favorites,
    selectedCategoryId: selectedId,
  }).map((category) => category.id)
  const candidateIds = selectedId === null || arrangedIds.includes(selectedId) ? arrangedIds : [...arrangedIds, selectedId]

  return {
    stripCategories,
    levelCategories,
    candidateIds,
    selectedId,
    /** Spread onto `<CategoryTabsPicker>`, with the `hiddenCount` the fit decides. */
    pickerProps: {
      categories: levelCategories,
      favoriteCategoryIds: favoritePositions,
      showOnlyFavorites: preferences.show_only_favorites,
      favoritesAreDefault: preferences.is_default,
      selectedCategoryId: selectedId,
      onSelect: (position: number) => onSelect(stripCategories[position].key),
      onToggleFavorite: (position: number) => toggleFavorite(categoryIdOf(position)),
      onSetFavorites: setListedFavorites,
      onShowOnlyFavoritesChange: setShowOnlyFavorites,
      level,
      onLevelChange: setLevel,
    },
  }
}
