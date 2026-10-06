import { useCallback, useState } from 'react'
import { useInvoiceEmailFlow } from '@/features/invoices/use-invoice-email-flow'
import { useInvoicePdfDownload } from '@/features/invoices/use-invoice-pdf-download'
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

  const { download } = useInvoicePdfDownload()
  const emailFlow = useInvoiceEmailFlow()
  const { startEmail, startReminder } = emailFlow

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
      case 'pdf':
        void download(id)
        break
      case 'email':
        startEmail(id)
        break
      case 'remind':
        startReminder(id)
        break
      default:
        break
    }
  }, [download, startEmail, startReminder])

  return {
    handleAction,
    emailFlow,
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
