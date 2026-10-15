import { PurchaseInvoiceDeleteDialog } from '@/features/purchase-invoices/purchase-invoice-delete-dialog'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import type { PurchaseInvoiceDeleteTarget } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'

interface PurchaseInvoiceActionDialogsProps {
  editId: number | null
  deleteTarget: PurchaseInvoiceDeleteTarget | null
  onCloseEdit: () => void
  onCloseDelete: () => void
  /** Called after the editor saved (the caller refreshes its grid). */
  onSaved: () => void
  /** Called after a successful delete (the caller refreshes its grid or leaves the record). */
  onDeleted: () => void
}

/** Lines editor and delete dialogs of a passive invoice, shared by the list and the detail screen. */
export function PurchaseInvoiceActionDialogs({
  editId,
  deleteTarget,
  onCloseEdit,
  onCloseDelete,
  onSaved,
  onDeleted,
}: PurchaseInvoiceActionDialogsProps) {
  return (
    <>
      {editId !== null ? (
        <PurchaseInvoiceEditorDialog
          mode="edit"
          invoiceId={editId}
          open
          onOpenChange={(open) => (open ? undefined : onCloseEdit())}
          onSaved={onSaved}
        />
      ) : null}
      <PurchaseInvoiceDeleteDialog target={deleteTarget} onClose={onCloseDelete} onDeleted={onDeleted} />
    </>
  )
}
