import { useQuery } from '@tanstack/react-query'
import { fetchTableConfig } from '@/features/table/api'

/** Optional scope narrowing a domain's config (spec 0064: request-management's category tabs). */
export interface TableConfigScope {
  /** The selected Product Category tab. Absent/`undefined` ⇒ the "Tutte" tab. */
  productCategoryId?: number
}

/** Query keys for the generic table feature, namespaced by domain (and scope, when given). */
export const tableKeys = {
  config: (domain: string, scope?: TableConfigScope) =>
    ['table', domain, 'config', scope?.productCategoryId ?? null] as const,
}

/**
 * Loads a domain's table schema. The config is semi-static (changes only with
 * permissions/schema), so it is cached aggressively to avoid refetching on every
 * mount while the grid streams rows separately via the SSRM datasource. The
 * query key includes the domain so each table caches independently.
 *
 * `scope.productCategoryId` (spec 0064) narrows the config to one Product
 * Category tab: the backend appends that category's `attr.<code>` columns and
 * the query key isolates the cache per category so switching tabs never shows
 * a stale column set.
 */
export function useTableConfig(domain: string, scope?: TableConfigScope) {
  const productCategoryId = scope?.productCategoryId
  return useQuery({
    queryKey: tableKeys.config(domain, scope),
    queryFn: () => fetchTableConfig(domain, productCategoryId),
    staleTime: 10 * 60 * 1000,
  })
}
