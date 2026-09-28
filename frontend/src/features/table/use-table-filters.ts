import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  resetTableFilters,
  saveTableFilters,
  type SaveTableFiltersPayload,
} from '@/features/table/api'
import { removeOtherScopeConfigs, tableKeys, type TableConfigScope } from '@/features/table/use-table-config'
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
 *
 * The filters are stored ONCE per domain, so every OTHER scope's cached config
 * is now outdated too: they are dropped (see `removeOtherScopeConfigs`) so
 * switching tab never shows, or queries with, the previous filters.
 */
export function useSaveTableFilters(domain: string, scope?: TableConfigScope) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (payload: SaveTableFiltersPayload) =>
      saveTableFilters(domain, payload, scope?.productCategoryId),
    onSuccess: (config: TableConfig) => {
      removeOtherScopeConfigs(queryClient, domain, scope)
      queryClient.setQueryData(tableKeys.config(domain, scope), config)
    },
    // A rejected save (e.g. a validation 422) must not pass silently: the
    // filters would otherwise vanish on the next reload with no warning.
    onError: () => {
      toast.error(t('table.filtersSaveError'))
    },
  })
}

/**
 * Resets the user's saved filters for a domain. The caller refetches the
 * config of its own `scope` and remounts the grid so the filters clear and the
 * SSRM re-queries unfiltered; every other scope's entry is dropped.
 */
export function useResetTableFilters(domain: string, scope?: TableConfigScope) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => resetTableFilters(domain),
    onSuccess: () => removeOtherScopeConfigs(queryClient, domain, scope),
  })
}
