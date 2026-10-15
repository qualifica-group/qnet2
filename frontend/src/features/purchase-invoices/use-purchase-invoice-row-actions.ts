import { useCallback, useState } from 'react'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'

export interface PurchaseInvoiceDeleteTarget {
  id: number
  numberLabel: string
}

/** Which dialog/sheet each row action opens; the table adapter and the detail screen render them from this state. */
export function usePurchaseInvoiceRowActions() {
  const [viewId, setViewId] = useState<number | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<PurchaseInvoiceDeleteTarget | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const handleAction: RowActionHandler = useCallback((action, row) => {
    const id = Number(row.id)
    switch (action.key) {
      case 'view':
        setViewId(id)
        break
      case 'delete':
        setDeleteTarget({ id, numberLabel: String(row.document_number ?? id) })
        break
      case 'activity':
        setActivityRow(row)
        break
      default:
        break
    }
  }, [])

  return {
    handleAction,
    openDelete: setDeleteTarget,
    viewId,
    closeView: useCallback(() => setViewId(null), []),
    deleteTarget,
    closeDelete: useCallback(() => setDeleteTarget(null), []),
    activityRow,
    closeActivity: useCallback(() => setActivityRow(null), []),
  }
}
