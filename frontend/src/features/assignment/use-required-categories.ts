import { useQuery } from '@tanstack/react-query'
import { fetchRequiredCategories } from '@/features/assignment/api'
import { assignmentKeys } from '@/features/assignment/query-keys'
import type { RequiredCategoriesPayload } from '@/features/assignment/types'

/**
 * The requirement of a fixed selection does not move while a dialog is open;
 * a refetch on window focus would flip the picker back to "resolving" under
 * the user's hands.
 */
const REQUIRED_CATEGORIES_STALE_TIME_MS = 5 * 60 * 1000

interface UseRequiredCategoriesOptions {
  /**
   * The selected records. `null` when nothing is selected: no request is
   * issued and no competence filter applies.
   */
  selection: RequiredCategoriesPayload | null
  /** Extra gate for the caller's own lifecycle (e.g. dialog closed). Default true. */
  enabled?: boolean
}

interface UseRequiredCategoriesResult {
  /**
   * Value to forward as `competence_category_ids` to the users for-select.
   * `undefined` means NO FILTER, and covers every case where filtering would
   * be wrong: nothing selected, still resolving, request failed, or the server
   * answered "no requirement" (empty union, spec 0110 AC-041).
   */
  competenceCategoryIds: number[] | undefined
  /** True while the union is being resolved: the picker must stay disabled. */
  isResolving: boolean
  /** True when the resolution failed; the caller decides how loudly to fail. */
  isError: boolean
}

/**
 * Resolves the product categories required by a selection of records
 * (spec 0110), so the operator picker can be narrowed to the users competent
 * for at least one of them. Server state, hence TanStack Query: the same
 * selection resolves once and is shared by every consumer.
 */
export function useRequiredCategories({
  selection,
  enabled = true,
}: UseRequiredCategoriesOptions): UseRequiredCategoriesResult {
  const { data, isLoading, isError } = useQuery({
    queryKey: assignmentKeys.requiredCategories(selection),
    queryFn: () => (selection === null ? [] : fetchRequiredCategories(selection)),
    enabled: enabled && selection !== null,
    staleTime: REQUIRED_CATEGORIES_STALE_TIME_MS,
  })

  return {
    competenceCategoryIds: data && data.length > 0 ? data : undefined,
    isResolving: isLoading,
    isError,
  }
}
