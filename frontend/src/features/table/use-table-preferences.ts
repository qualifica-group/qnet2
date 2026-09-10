import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { ColumnState } from 'ag-grid-community'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  resetTablePreferences,
  saveTablePreferences,
} from '@/features/table/api'
import { tableKeys, type TableConfigScope } from '@/features/table/use-table-config'
import type { ColumnPreferenceInput } from '@/features/table/types'

/**
 * Bounds of the server's `columns.*.width` rule (integer, min:50, max:1000 — see
 * TablePreferencesRequest). AG Grid does NOT keep a dragged width inside them: a
 * manual resize is `startWidth + pointer delta`, neither rounded nor capped, so
 * under browser zoom or on a HiDPI trackpad it lands on a fractional pixel, and a
 * wide drag sails past the cap. Either one fails validation, and since a single
 * offending column 422s the WHOLE payload, one dragged column used to discard the
 * entire layout — order and visibility included.
 */
export const MIN_COLUMN_WIDTH = 50
export const MAX_COLUMN_WIDTH = 1000

function toPersistableWidth(width: number): number {
  return Math.min(Math.max(Math.round(width), MIN_COLUMN_WIDTH), MAX_COLUMN_WIDTH)
}

/**
 * Translate AG Grid's column state into the preferences payload (0003).
 *
 * Pure and side-effect free so it is unit-testable without a live grid:
 *  - only columns in `knownColumnIds` (the domain's declared columns) are
 *    emitted; synthetic grid columns — the row-actions column and, on tables
 *    with selection enabled, AG Grid's own 'ag-Grid-SelectionColumn' — are
 *    dropped. They are not in the server's `Rule::in` allow-list, so including
 *    even one would 422 the whole save and silently lose every change;
 *  - `order` is the column's current display position;
 *  - `visible` is the inverse of AG Grid's `hide`;
 *  - `width` is sent only when it is a real number, rounded and clamped into the
 *    range the server accepts (omitted, never null, so it satisfies `sometimes`).
 *
 * The backend computes the sparse delta from this full state, so the frontend
 * does not need to know the defaults.
 */
export function toColumnPreferences(
  state: ColumnState[],
  knownColumnIds: ReadonlySet<string>,
): ColumnPreferenceInput[] {
  return state
    .filter((column) => knownColumnIds.has(column.colId))
    .map((column, index) => {
      const preference: ColumnPreferenceInput = {
        id: column.colId,
        visible: !column.hide,
        order: index,
      }

      if (typeof column.width === 'number' && Number.isFinite(column.width)) {
        preference.width = toPersistableWidth(column.width)
      }

      return preference
    })
}

/**
 * Upserts the user's column layout for a domain. On success the returned merged
 * config refreshes the cache, so a remount within the config staleTime restores
 * the just-saved layout rather than the stale default.
 *
 * `scope` must be the SAME one the config query was keyed by (spec 0064's
 * category tabs): the cache is keyed per scope, so refreshing the unscoped key
 * from a scoped table would leave the entry the grid actually reads stale. It
 * is ALSO sent to the server, so the config written back into that per-tab
 * entry is the scoped shape — otherwise the response's unscoped column set
 * would drop the tab's `attr.*` columns out of the live grid.
 */
export function useSaveTablePreferences(domain: string, scope?: TableConfigScope) {
  const queryClient = useQueryClient()
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (columns: ColumnPreferenceInput[]) =>
      saveTablePreferences(domain, columns, scope?.productCategoryId),
    onSuccess: (config) => {
      queryClient.setQueryData(tableKeys.config(domain, scope), config)
    },
    // Surface a rejected persist (e.g. a validation 422) instead of swallowing
    // it: without this the layout silently reverts to the default on reload.
    onError: () => {
      toast.error(t('table.layoutError'))
    },
  })
}

/**
 * Resets the user's column layout for a domain to the PHP default. The caller
 * refetches the config and remounts the grid so the defaults take effect.
 */
export function useResetTablePreferences(domain: string) {
  return useMutation({
    mutationFn: () => resetTablePreferences(domain),
  })
}
