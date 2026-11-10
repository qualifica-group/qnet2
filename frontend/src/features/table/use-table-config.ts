import { useQuery, type QueryClient } from '@tanstack/react-query'
import { fetchTableConfig, type TableConfigTab } from '@/features/table/api'

/** Optional scope narrowing a domain's config (spec 0064: request-management's category tabs). */
export interface TableConfigScope {
  /** The selected Product Category tab. Absent/`undefined` ⇒ the "Tutte" tab. */
  productCategoryId?: number
  /**
   * A ROOT Product Category tab (spec 0337): rows and distinct values of its whole branch, and the config with
   * that root's own `attr.*` columns. Saved layout, saved filters and export are not per-tab.
   */
  productCategoryBranchId?: number
  /**
   * The page's grid takes the flexible-field columns of the category its saved filters select (spec 0374): the
   * config is asked with `attribute_columns_from_filters`. Only the list pages set it, never a grid inside a record.
   */
  attributeColumnsFromFilters?: boolean
}

/** Stable scope of the list pages whose `attr.*` columns follow the category filter (spec 0374). */
export const ATTRIBUTE_COLUMNS_FROM_FILTERS_SCOPE: TableConfigScope = { attributeColumnsFromFilters: true }

const ATTRIBUTE_COLUMNS_CACHE_KEY = 'filter-attributes'

/** The scope as the config endpoints take it; a root tab wins over a single category, which it excludes anyway. */
export function tableConfigTab(scope?: TableConfigScope): TableConfigTab | undefined {
  if (scope?.productCategoryBranchId != null) return { branchId: scope.productCategoryBranchId }
  if (scope?.productCategoryId != null) return scope.productCategoryId
  return scope?.attributeColumnsFromFilters ? { attributeColumnsFromFilters: true } : undefined
}

/** The cache-key segment that tells one scope's config from another's. */
function scopeCacheKey(scope?: TableConfigScope): string | number | null {
  if (scope?.productCategoryBranchId != null) return `root-${scope.productCategoryBranchId}`
  if (scope?.productCategoryId != null) return scope.productCategoryId
  return scope?.attributeColumnsFromFilters ? ATTRIBUTE_COLUMNS_CACHE_KEY : null
}

/** Query keys for the generic table feature, namespaced by domain (and scope, when given). */
export const tableKeys = {
  /** Prefix of every scope's config entry for one domain. */
  configs: (domain: string) => ['table', domain, 'config'] as const,
  config: (domain: string, scope?: TableConfigScope) =>
    // A root tab and the same category at the "all" level have different configs (no manager relabel on the root).
    ['table', domain, 'config', scopeCacheKey(scope)] as const,
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
 * `scope` (spec 0064) narrows the config to one Product Category tab, single
 * or root: the backend appends that category's `attr.<code>` columns and the
 * query key isolates the cache per tab so switching tabs never shows a stale
 * column set.
 */
export function useTableConfig(domain: string, scope?: TableConfigScope) {
  const tab = tableConfigTab(scope)
  return useQuery({
    queryKey: tableKeys.config(domain, scope),
    queryFn: () => fetchTableConfig(domain, tab),
    staleTime: 10 * 60 * 1000,
  })
}
