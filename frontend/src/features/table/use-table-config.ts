import { useQuery, type QueryClient } from '@tanstack/react-query'
import { fetchTableConfig } from '@/features/table/api'

/** Optional scope narrowing a domain's config (spec 0064: request-management's category tabs). */
export interface TableConfigScope {
  /** The selected Product Category tab. Absent/`undefined` ⇒ the "Tutte" tab. */
  productCategoryId?: number
}

/** Query keys for the generic table feature, namespaced by domain (and scope, when given). */
export const tableKeys = {
  /** Prefix of every scope's config entry for one domain. */
  configs: (domain: string) => ['table', domain, 'config'] as const,
  config: (domain: string, scope?: TableConfigScope) =>
    ['table', domain, 'config', scope?.productCategoryId ?? null] as const,
}

/**
 * Marks every scope's cached config of `domain` stale without refetching the
 * inactive ones now: layout and filters are stored once per domain, while the
 * config is cached per category tab.
 */
export function invalidateDomainConfigs(queryClient: QueryClient, domain: string): Promise<void> {
  return queryClient.invalidateQueries({ queryKey: tableKeys.configs(domain), refetchType: 'none' })
}

/**
 * Drops every OTHER scope's cached config of `domain` after a filter save or
 * reset. Invalidating is not enough here: a stale entry is still served on
 * the tab's next mount, and the grid applies its filters ONCE at creation, so
 * it would query with the old filters while the fresh config arrives. With
 * the entry gone the tab loads the fresh config before building the grid.
 */
export function removeOtherScopeConfigs(queryClient: QueryClient, domain: string, scope?: TableConfigScope): void {
  const keep = tableKeys.config(domain, scope)
  queryClient.removeQueries({
    queryKey: tableKeys.configs(domain),
    predicate: (query) => query.queryKey[3] !== keep[3],
  })
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
