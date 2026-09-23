import { Fragment, useCallback, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { GridApi } from 'ag-grid-community'
import { ChevronDown, Trash2, type LucideIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useBulkDelete } from '@/features/table/use-bulk-delete'
import type { TableActionDefinition, TableRow, TableRowId } from '@/features/table/types'

/** The current selection, ids alongside their full row data (spec 0048 AC-031). */
export interface TableSelection {
  ids: TableRowId[]
  rows: TableRow[]
}

/** Empty selection (module-level so its identity is stable across renders). */
const EMPTY_SELECTION: TableSelection = { ids: [], rows: [] }

/**
 * One entry in the bulk-actions dropdown. Domain adapters return these from
 * `getBulkActions`; the built-in "delete selected" is appended as a
 * destructive one. Icon is a lucide component so the slot renders it inline.
 */
export interface BulkAction {
  key: string
  label: string
  icon?: LucideIcon
  onSelect: () => void
  destructive?: boolean
  disabled?: boolean
}

interface UseBulkActionsSlotArgs {
  domain: string
  gridApi: GridApi | null
  /** The domain's action catalog (already permission-filtered server-side). */
  actions: TableActionDefinition[] | undefined
  /** Purges and reloads the SSRM cache after a bulk-delete succeeds. */
  refresh: () => void
  /**
   * Extra bulk action(s), supplied by the domain adapter (spec 0048 AC-041),
   * built from the current selection (ids AND their row data — AC-031 needs
   * the latter, e.g. to precompile a popup field when the selection shares
   * one) and merged into the single actions dropdown alongside the built-in
   * "delete selected".
   */
  getBulkActions?: (selection: TableSelection) => BulkAction[]
}

interface UseBulkActionsSlotResult {
  selectedIds: TableRowId[]
  onSelectionChanged: (selection: TableSelection) => void
  /** Empties the selection (call after any bulk action succeeds). */
  clearSelection: () => void
  /** True while the checkbox column should be enabled: either bulk-delete is available, or the adapter wired its own action. */
  enableSelection: boolean
  bulkActionsSlot: ReactNode
}

/**
 * Owns the generic table's bulk-selection state and the toolbar slot built
 * from it (spec 0009's "delete selected", extended by spec 0048's
 * domain-supplied actions). Every bulk action — the built-in delete and any
 * domain one — is rendered inside a single "Actions" dropdown, never a row of
 * loose buttons (client directive 2026-07-21). Split out of `TableView`
 * (engineering.md §6): a single, self-contained concern with its own state,
 * one mutation (bulk-delete) and one render.
 */
export function useBulkActionsSlot({
  domain,
  gridApi,
  actions,
  refresh,
  getBulkActions,
}: UseBulkActionsSlotArgs): UseBulkActionsSlotResult {
  const { t } = useTranslation()
  const [selection, setSelection] = useState<TableSelection>(EMPTY_SELECTION)
  const selectedIds = selection.ids

  const canBulkDelete = useMemo(
    () => actions?.some((action) => action.key === 'delete') ?? false,
    [actions],
  )
  const { runBulkDelete, isDeleting } = useBulkDelete({ domain, gridApi, refresh })

  const handleBulkDelete = useCallback(async () => {
    // Bulk-delete stays number-only (D-6): notifications has no delete action,
    // so filtering out non-numeric ids changes nothing for every domain that
    // actually offers it.
    const numericIds = selectedIds.filter((id): id is number => typeof id === 'number')
    const didDelete = await runBulkDelete(numericIds)
    if (didDelete) {
      setSelection(EMPTY_SELECTION)
    }
  }, [runBulkDelete, selectedIds])

  const clearSelection = useCallback(() => setSelection(EMPTY_SELECTION), [])

  // The domain's extra actions first, then the built-in destructive delete.
  const items = useMemo<BulkAction[]>(() => {
    const domainItems = getBulkActions?.(selection) ?? []
    if (!canBulkDelete) {
      return domainItems
    }
    return [
      ...domainItems,
      {
        key: 'delete',
        label: t('table.deleteSelected', { count: selectedIds.length }),
        icon: Trash2,
        destructive: true,
        disabled: isDeleting,
        onSelect: () => void handleBulkDelete(),
      },
    ]
  }, [getBulkActions, selection, selectedIds, canBulkDelete, t, isDeleting, handleBulkDelete])

  const bulkActionsSlot =
    selectedIds.length > 0 && items.length > 0 ? (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button type="button" variant="secondary" size="sm" className="shrink-0">
            {t('table.bulkActions', { count: selectedIds.length })}
            <ChevronDown aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {items.map((item, index) => {
            const previous = items[index - 1]
            const needsSeparator = Boolean(item.destructive && previous && !previous.destructive)
            return (
              <Fragment key={item.key}>
                {needsSeparator ? <DropdownMenuSeparator /> : null}
                <DropdownMenuItem
                  variant={item.destructive ? 'destructive' : 'default'}
                  disabled={item.disabled}
                  onSelect={() => item.onSelect()}
                >
                  {item.icon ? <item.icon aria-hidden="true" /> : null}
                  {item.label}
                </DropdownMenuItem>
              </Fragment>
            )
          })}
        </DropdownMenuContent>
      </DropdownMenu>
    ) : null

  return {
    selectedIds,
    onSelectionChanged: setSelection,
    clearSelection,
    enableSelection: canBulkDelete || Boolean(getBulkActions),
    bulkActionsSlot,
  }
}
