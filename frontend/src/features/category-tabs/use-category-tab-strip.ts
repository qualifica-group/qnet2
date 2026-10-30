import { useCallback } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchGridCategoryTabs } from '@/features/category-tabs/api'
import { levelArgs, useCategoryTabLevel } from '@/features/category-tabs/category-tab-level'
import { categoryTabKeys } from '@/features/category-tabs/query-keys'
import { useCategoryTabPreferences } from '@/features/category-tabs/use-category-tab-preferences'
import { useCategoryTabSelection } from '@/features/category-tabs/use-category-tab-selection'
import type { CategoryTabItem, CategoryTabLevel, CategoryTabSource } from '@/features/category-tabs/types'

/** Stable empty reference so a pending/empty categories query never creates a fresh array every render. */
const EMPTY_CATEGORIES: CategoryTabItem[] = []

/**
 * Everything a grid needs to drive its category strip: the categories (query),
 * the persisted selection reconciled with them, the favourites, and the
 * callback that re-reads the counts after a write.
 *
 * `fetchCategories` is only passed by Gestione Richieste, whose endpoint names
 * the count `requests_count`; it must be a module-level function (stable).
 *
 * The categories are never held fresh: a write on a row moves its counts, so
 * they are re-read each time the grid mounts while the cached copy paints the
 * strip meanwhile. A persisted id no longer among the categories falls back to
 * "Tutte", computed on every render rather than corrected in an effect
 * (spec 0064 AC-021).
 *
 * The strip lists every category or only the root ones (spec 0337): the level
 * is persisted, each level keeps its own tab and favourites, and the grid
 * scope follows the level — `productCategoryId` for `all`, `productCategoryBranchId`
 * for a root tab. `selectedCategoryId` stays the single-category tab only
 * (`null` on "Tutte" and on a root tab), so what is category-specific (the
 * `attr.*` columns, the G.A. relabel) never applies to a branch (D-3).
 */
export function useCategoryTabStrip(
  source: CategoryTabSource,
  fetchCategories: (apiBasePath: string, level?: CategoryTabLevel) => Promise<CategoryTabItem[]> = fetchGridCategoryTabs,
) {
  const queryClient = useQueryClient()
  const sourceKey = source.key
  const { level, setLevel } = useCategoryTabLevel(sourceKey)
  const categoriesQuery = useQuery({
    queryKey: categoryTabKeys.categories(sourceKey, level),
    queryFn: () => fetchCategories(source.apiBasePath, ...levelArgs(level)),
  })
  const { categoryId: storedCategoryId, setCategoryId } = useCategoryTabSelection(sourceKey, level)
  const preferences = useCategoryTabPreferences(source, level)

  const categories = categoriesQuery.data ?? EMPTY_CATEGORIES
  const selectedTabId =
    storedCategoryId !== null && categories.some((category) => category.id === storedCategoryId)
      ? storedCategoryId
      : null
  const isRoot = level === 'root'

  const invalidateCategories = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: categoryTabKeys.categoriesPrefix(sourceKey) })
  }, [queryClient, sourceKey])

  return {
    selectedCategoryId: isRoot ? null : selectedTabId,
    /** The `scope` of the `<TableView>`; `undefined` on "Tutte". */
    scope:
      selectedTabId === null
        ? undefined
        : isRoot
          ? { productCategoryBranchId: selectedTabId }
          : { productCategoryId: selectedTabId },
    /** The `key` of the `<TableView>`: a tab switch remounts the table (spec 0064 D-4). */
    tableKey: selectedTabId === null ? 'all' : isRoot ? `root-${selectedTabId}` : selectedTabId,
    isPending: categoriesQuery.isPending,
    invalidateCategories,
    /** Spread onto `<CategoryTabs>`. */
    tabsProps: {
      categories,
      selectedCategoryId: selectedTabId,
      onSelect: setCategoryId,
      level,
      onLevelChange: setLevel,
      favoriteCategoryIds: preferences.preferences.favorite_category_ids,
      showOnlyFavorites: preferences.preferences.show_only_favorites,
      favoritesAreDefault: preferences.preferences.is_default,
      onToggleFavorite: preferences.toggleFavorite,
      onShowOnlyFavoritesChange: preferences.setShowOnlyFavorites,
    },
  }
}
