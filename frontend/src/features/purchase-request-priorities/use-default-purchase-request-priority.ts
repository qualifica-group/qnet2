import { useQuery } from '@tanstack/react-query'
import { fetchPurchaseRequestPrioritiesForSelect } from '@/features/purchase-request-priorities/for-select-api'

/** Matches the for-select server cap, so a catalog larger than the default page still resolves its default. */
const DEFAULT_LOOKUP_FETCH_LIMIT = 100

/**
 * Id of the catalog's default priority (at most one row carries `meta.is_default`,
 * server-enforced), used to precompile the new RDA form. `enabled` is false on edit.
 */
export function useDefaultPurchaseRequestPriorityId(enabled: boolean): number | null {
  const query = useQuery({
    queryKey: ['purchase-request-priorities', 'default'],
    queryFn: async () => {
      const response = await fetchPurchaseRequestPrioritiesForSelect({ limit: DEFAULT_LOOKUP_FETCH_LIMIT })
      return response.items.find((item) => item.meta.is_default)?.id ?? null
    },
    enabled,
  })
  return query.data ?? null
}
