import { useCallback, useState } from 'react'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'

export interface InvoiceDeleteTarget {
  id: number
  numberLabel: string
}

/** Which dialog/sheet each row action opens; the table adapter renders them from this state. */
export function useInvoiceRowActions() {
  const [viewId, setViewId] = useState<number | null>(null)
  const [editId, setEditId] = useState<number | null>(null)
  const [detailsId, setDetailsId] = useState<number | null>(null)
  const [deleteTarget, setDeleteTarget] = useState<InvoiceDeleteTarget | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const handleAction: RowActionHandler = useCallback((action, row) => {
    const id = Number(row.id)
    switch (action.key) {
      case 'view':
        setViewId(id)
        break
      case 'update':
        setEditId(id)
        break
      case 'details':
        setDetailsId(id)
        break
      case 'delete':
        setDeleteTarget({ id, numberLabel: String(row.number_label ?? id) })
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
    viewId,
    closeView: useCallback(() => setViewId(null), []),
    editId,
    closeEdit: useCallback(() => setEditId(null), []),
    detailsId,
    closeDetails: useCallback(() => setDetailsId(null), []),
    deleteTarget,
    closeDelete: useCallback(() => setDeleteTarget(null), []),
    activityRow,
    closeActivity: useCallback(() => setActivityRow(null), []),
  }
}
