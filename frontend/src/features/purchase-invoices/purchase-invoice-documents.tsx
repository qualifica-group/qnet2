import { DocumentsSection } from '@/features/attachments/documents-section'
import { useAbilities } from '@/features/auth/use-abilities'

/** `attachable_type` alias of `config/attachments.php` (spec 0250 D-3). */
export const PURCHASE_INVOICE_ATTACHABLE = 'purchase_invoice'

/** Supplier documents of a saved passive invoice, through the shared attachment manager. */
export function PurchaseInvoiceDocuments({ invoiceId }: { invoiceId: number }) {
  const { can } = useAbilities()

  return (
    <DocumentsSection
      resource={PURCHASE_INVOICE_ATTACHABLE}
      id={invoiceId}
      canUpload={can('attachments.create')}
      canDelete={can('attachments.delete')}
    />
  )
}
