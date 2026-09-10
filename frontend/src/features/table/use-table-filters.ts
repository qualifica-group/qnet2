import { useMutation, useQueryClient } from '@tanstack/react-query'
import {
  resetTableFilters,
  saveTableFilters,
  type SaveTableFiltersPayload,
} from '@/features/table/api'
import { tableKeys, type TableConfigScope } from '@/features/table/use-table-config'
import type { TableConfig } from '@/features/table/types'

/**
 * Upserts the user's applied filterModel and/or advanced filters (spec 0032)
 * for a domain so they survive a reload; each key persists independently. On
 * success the returned merged config refreshes the cache, so a remount within
 * the config staleTime restores the just-saved state rather than the stale
 * default.
 *
 * `scope` must be the SAME one the config query was keyed by (spec 0064's
 * category tabs): the cache is keyed per scope, so refreshing the unscoped key
 * from a scoped table would leave the entry the grid actually reads stale. It
 * is ALSO sent to the server, so the config written back into that per-tab
 * entry is the scoped shape — otherwise the response's unscoped column set
 * would drop the tab's `attr.*` columns out of the live grid.
 */
export function useSaveTableFilters(domain: string, scope?: TableConfigScope) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SaveTableFiltersPayload) =>
      saveTableFilters(domain, payload, scope?.productCategoryId),
    onSuccess: (config: TableConfig) => {
      queryClient.setQueryData(tableKeys.config(domain, scope), config)
    },
  })
}

/**
 * Resets the user's saved filters for a domain. The caller refetches the config
 * and remounts the grid so the filters clear and the SSRM re-queries unfiltered.
 */
export function useResetTableFilters(domain: string) {
  return useMutation({
    mutationFn: () => resetTableFilters(domain),
  })
}
