import { useCallback } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchGridCategoryTabs } from '@/features/category-tabs/api'
import { categoryTabKeys } from '@/features/category-tabs/query-keys'
import { useCategoryTabPreferences } from '@/features/category-tabs/use-category-tab-preferences'
import { useCategoryTabSelection } from '@/features/category-tabs/use-category-tab-selection'
import type { CategoryTabItem, CategoryTabSource } from '@/features/category-tabs/types'

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
 */
export function useCategoryTabStrip(
  source: CategoryTabSource,
  fetchCategories: (apiBasePath: string) => Promise<CategoryTabItem[]> = fetchGridCategoryTabs,
) {
  const queryClient = useQueryClient()
  const sourceKey = source.key
  const categoriesQuery = useQuery({
    queryKey: categoryTabKeys.categories(sourceKey),
    queryFn: () => fetchCategories(source.apiBasePath),
  })
  const { categoryId: storedCategoryId, setCategoryId } = useCategoryTabSelection(source.key)
  const preferences = useCategoryTabPreferences(source)

  const categories = categoriesQuery.data ?? EMPTY_CATEGORIES
  const selectedCategoryId =
    storedCategoryId !== null && categories.some((category) => category.id === storedCategoryId)
      ? storedCategoryId
      : null

  const invalidateCategories = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: categoryTabKeys.categories(sourceKey) })
  }, [queryClient, sourceKey])

  return {
    selectedCategoryId,
    isPending: categoriesQuery.isPending,
    invalidateCategories,
    /** Spread onto `<CategoryTabs>`. */
    tabsProps: {
      categories,
      selectedCategoryId,
      onSelect: setCategoryId,
      favoriteCategoryIds: preferences.preferences.favorite_category_ids,
      showOnlyFavorites: preferences.preferences.show_only_favorites,
      favoritesAreDefault: preferences.preferences.is_default,
      onToggleFavorite: preferences.toggleFavorite,
      onShowOnlyFavoritesChange: preferences.setShowOnlyFavorites,
    },
  }
}
