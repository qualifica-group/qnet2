import { useRequestManagementCategories } from '@/features/request-management/use-request-management-categories'
import { useRequestManagementCategoryPreference } from '@/features/request-management/use-request-management-category-preference'
import type { RequestManagementProductCategory } from '@/features/request-management/types'

/** Stable empty reference so a pending/empty categories query never creates a fresh array every render. */
const EMPTY_CATEGORIES: RequestManagementProductCategory[] = []

/**
 * Owns the Gestione Richieste category tab strip's selection (spec 0064): the
 * categories list (query), the persisted preference (localStorage) and their
 * reconciliation — a persisted category id no longer among those the actor
 * can see today silently falls back to "Tutte" (D-2/AC-021), computed fresh
 * on every render rather than corrected in an effect.
 */
export function useRequestManagementCategoryTab() {
  const categoriesQuery = useRequestManagementCategories()
  const { categoryId: storedCategoryId, setCategoryId } = useRequestManagementCategoryPreference()

  const categories = categoriesQuery.data ?? EMPTY_CATEGORIES
  const selectedCategoryId =
    storedCategoryId !== null && categories.some((category) => category.id === storedCategoryId)
      ? storedCategoryId
      : null

  return {
    categories,
    selectedCategoryId,
    setCategoryId,
    isPending: categoriesQuery.isPending,
  }
}
