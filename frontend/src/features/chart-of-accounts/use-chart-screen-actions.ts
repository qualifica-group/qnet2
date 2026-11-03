import { useState } from 'react'
import { useDeleteEntry } from '@/features/chart-of-accounts/use-delete-entry'
import type { RowActionKey } from '@/features/chart-of-accounts/tree-actions'
import type { TreeRow } from '@/features/chart-of-accounts/tree-model'
import type { SheetState } from '@/features/chart-of-accounts/sheet-state'
import { CHART_ACCOUNTS_DOMAIN, REVENUE_TYPES_DOMAIN } from '@/features/chart-of-accounts/types'
import { COST_TYPES_DOMAIN } from '@/features/cost-types/types'

/** Activity-log registry key of the resource a row belongs to. */
function activityResource(row: TreeRow): string {
  if (row.type !== 'sub') return CHART_ACCOUNTS_DOMAIN
  return row.node.source === 'revenue_type' ? REVENUE_TYPES_DOMAIN : COST_TYPES_DOMAIN
}

function rowId(row: TreeRow): number {
  return row.type === 'unplaced' ? 0 : row.node.id
}

/** Sheet to open for a row action (null when the action opens nothing, e.g. delete). */
function sheetFor(action: RowActionKey, row: TreeRow): SheetState | null {
  switch (action) {
    case 'newAccount':
      return row.type === 'group' ? { type: 'account-create', group: row.node } : null
    case 'newSubAccount':
      return row.type === 'account' ? { type: 'sub-create', account: row.node, group: row.group } : null
    case 'edit':
    case 'place':
      if (row.type === 'group') return { type: 'group-edit', group: row.node }
      if (row.type === 'account') return { type: 'account-edit', account: row.node, group: row.group }
      if (row.type === 'sub') return { type: 'sub-edit', sub: row.node, account: row.account, group: row.group }
      return null
    case 'activity':
      return row.type === 'unplaced' ? null : { type: 'activity', resource: activityResource(row), id: rowId(row) }
    case 'delete':
      return null
  }
}

function labelOf(row: TreeRow): string {
  return row.type === 'unplaced' ? '' : [row.node.code, row.node.name].filter(Boolean).join(' ')
}

/** Which overlay is open and what a row action does; delete is confirmed and run here. */
export function useChartScreenActions() {
  const [sheet, setSheet] = useState<SheetState | null>(null)
  const deleteEntry = useDeleteEntry()

  const handleAction = (action: RowActionKey, row: TreeRow) => {
    if (action === 'delete') {
      void deleteEntry(row, labelOf(row))
      return
    }
    setSheet(sheetFor(action, row))
  }

  return {
    sheet,
    closeSheet: () => setSheet(null),
    openNewGroup: () => setSheet({ type: 'group-create' }),
    handleAction,
  }
}
