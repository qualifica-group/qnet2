import type { RequiredCategoriesPayload } from '@/features/assignment/types'

/**
 * Query keys for the cross-domain assignment helpers (spec 0110). The whole
 * payload joins the key: the answer is a pure function of the selection, so
 * two different selections must never share a cache entry (TanStack hashes
 * object keys deterministically, regardless of property order). A `null`
 * payload is the key of the idle, disabled query (nothing selected yet).
 */
export const assignmentKeys = {
  all: ['assignment'] as const,
  requiredCategories: (payload: RequiredCategoriesPayload | null) =>
    ['assignment', 'required-categories', payload] as const,
}
