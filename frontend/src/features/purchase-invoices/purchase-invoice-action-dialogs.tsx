import { PurchaseInvoiceDeleteDialog } from '@/features/purchase-invoices/purchase-invoice-delete-dialog'
import { PurchaseInvoiceDetailsDialog } from '@/features/purchase-invoices/purchase-invoice-details-dialog'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import { usePurchaseInvoice } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { PurchaseInvoiceDeleteTarget } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'

/** The supplier-details dialog needs the whole document: read it (cached) once an id is chosen. */
function PurchaseInvoiceDetailsHost({ invoiceId, onClose }: { invoiceId: number; onClose: () => void }) {
  const { data } = usePurchaseInvoice(invoiceId)

  return data ? (
    <PurchaseInvoiceDetailsDialog invoice={data} open onOpenChange={(open) => (open ? undefined : onClose())} />
  ) : null
}

interface PurchaseInvoiceActionDialogsProps {
  editId: number | null
  detailsId: number | null
  deleteTarget: PurchaseInvoiceDeleteTarget | null
  onCloseEdit: () => void
  onCloseDetails: () => void
  onCloseDelete: () => void
  /** Called after the editor saved (the caller refreshes its grid). */
  onSaved: () => void
  /** Called after a successful delete (the caller refreshes its grid or leaves the record). */
  onDeleted: () => void
}

/** Edit / supplier details / delete dialogs of a passive invoice, shared by the list and the detail screen. */
export function PurchaseInvoiceActionDialogs({
  editId,
  detailsId,
  deleteTarget,
  onCloseEdit,
  onCloseDetails,
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
      {detailsId !== null ? <PurchaseInvoiceDetailsHost invoiceId={detailsId} onClose={onCloseDetails} /> : null}
      <PurchaseInvoiceDeleteDialog target={deleteTarget} onClose={onCloseDelete} onDeleted={onDeleted} />
    </>
  )
}
