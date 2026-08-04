import { useQuery } from '@tanstack/react-query'
import { fetchCategoryManagerLabels } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import type { ManagerLabels } from '@/features/request-management/types'

/**
 * Resolves the currently active Product Category tab's effective G.A. labels
 * (spec 0080) — the source for the create form's "Operatore" field, which has
 * no persisted request yet to carry its own `manager_labels` (unlike the work
 * panel). Reads the RAW stored tab preference, not reconciled against the
 * actor's currently visible categories (that reconciliation is
 * `useRequestManagementCategoryTab`'s own concern, spec 0064 D-2): a stale or
 * inaccessible id simply resolves to no override (a 403/404 leaves `data`
 * `undefined`), an acceptable outcome for a purely cosmetic relabeling.
 * `null` (the "Tutte" tab) skips the fetch entirely — there is no category to
 * resolve from.
 */
export function useActiveCategoryManagerLabels(categoryId: number | null) {
  return useQuery<ManagerLabels>({
    queryKey: requestManagementKeys.categoryManagerLabels(categoryId),
    queryFn: () => fetchCategoryManagerLabels(categoryId as number),
    enabled: categoryId != null,
    staleTime: 5 * 60 * 1000,
    retry: false,
  })
}
