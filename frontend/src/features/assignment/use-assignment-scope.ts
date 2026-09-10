import { useQuery } from '@tanstack/react-query'
import { fetchAssignmentScope } from '@/features/assignment/api'
import { assignmentKeys } from '@/features/assignment/query-keys'
import type {
  AssignmentScopePayload,
  AssignmentScopeResult,
} from '@/features/assignment/types'

/**
 * The scope of a fixed selection does not move while a dialog is open; a
 * refetch on window focus would flip the picker back to "resolving" under the
 * user's hands.
 */
const ASSIGNMENT_SCOPE_STALE_TIME_MS = 5 * 60 * 1000

/** Never delivered: the query is disabled while the selection is `null`. */
const EMPTY_SCOPE: AssignmentScopeResult = {
  product_category_ids: [],
  operational_site_id: null,
  campaign_ids: [],
  single_operator_available: true,
}

interface UseAssignmentScopeOptions {
  /**
   * The selected records. `null` when nothing is selected: no request is
   * issued and no filter applies.
   */
  selection: AssignmentScopePayload | null
  /** Extra gate for the caller's own lifecycle (e.g. dialog closed). Default true. */
  enabled?: boolean
}

interface UseAssignmentScopeResult {
  /**
   * Value to forward as `competence_category_ids` to the users for-select.
   * `undefined` means NO FILTER, and covers every case where filtering would
   * be wrong: nothing selected, still resolving, request failed, or the server
   * answered "no requirement" (empty union, spec 0110 AC-041).
   */
  competenceCategoryIds: number[] | undefined
  /**
   * Value to forward as `operational_site_id`. `undefined` while unresolved or
   * on failure: the picker stays disabled and no unfiltered request goes out
   * (spec 0113 AC-034). `null` is a RESOLVED answer — the selection has no
   * shared site (mixed, or one record unresolvable) — and means "omit the site
   * filter", not "no site".
   */
  operationalSiteId: number | null | undefined
  /**
   * Distinct campaigns of the selection, `undefined` while unresolved or on
   * failure. More than one campaign disables single-operator assignment in the
   * import wizard (spec 0113 D-5).
   */
  campaignIds: number[] | undefined
  /**
   * True when at least one operator covers EVERY record of the selection.
   * `undefined` while unresolved or on failure — an unknown state, never a
   * negative one: the caller must not read it as "no operator available".
   * Explicit `false` is what disables single-operator assignment on Gestione
   * richieste, where a mixed Sede/product selection has no common operator.
   */
  singleOperatorAvailable: boolean | undefined
  /** True while the scope is being resolved: the picker must stay disabled. */
  isResolving: boolean
  /** True when the resolution failed; the caller decides how loudly to fail. */
  isError: boolean
}

/**
 * Resolves the assignment scope of a selection of records (spec 0113), so the
 * operator picker can be narrowed to the users of the selection's site who are
 * competent for its categories. Server state, hence TanStack Query: the same
 * selection resolves once and is shared by every consumer.
 */
export function useAssignmentScope({
  selection,
  enabled = true,
}: UseAssignmentScopeOptions): UseAssignmentScopeResult {
  const { data, isLoading, isError } = useQuery({
    queryKey: assignmentKeys.selectionScope(selection),
    queryFn: () => (selection === null ? EMPTY_SCOPE : fetchAssignmentScope(selection)),
    enabled: enabled && selection !== null,
    staleTime: ASSIGNMENT_SCOPE_STALE_TIME_MS,
  })

  const categoryIds = data?.product_category_ids

  return {
    competenceCategoryIds: categoryIds && categoryIds.length > 0 ? categoryIds : undefined,
    operationalSiteId: data?.operational_site_id,
    campaignIds: data?.campaign_ids,
    singleOperatorAvailable: data?.single_operator_available,
    isResolving: isLoading,
    isError,
  }
}
