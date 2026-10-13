import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { PurchaseInvoiceEditorForm } from '@/features/purchase-invoices/purchase-invoice-editor-form'
import { usePurchaseInvoiceEditorSource } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'

export interface PurchaseInvoiceEditorDialogProps {
  mode: 'create' | 'edit'
  /** Document to edit (edit mode). */
  invoiceId?: number
  /** ODAs to register (create mode): the rows are preloaded from their draft. */
  purchaseOrderIds?: number[]
  open: boolean
  onOpenChange: (open: boolean) => void
  onSaved?: (invoice: PurchaseInvoice) => void
}

/**
 * Create / edit modal of a passive invoice (spec 0250). Loads the document or
 * the ODA draft, then mounts the form so its defaults are the loaded data. The
 * form is unmounted on close: reopening always starts from a fresh read.
 */
export function PurchaseInvoiceEditorDialog({
  mode,
  invoiceId,
  purchaseOrderIds,
  open,
  onOpenChange,
  onSaved,
}: PurchaseInvoiceEditorDialogProps) {
  const { t } = useTranslation()
  const isEdit = mode === 'edit'
  const { source, isLoading, error } = usePurchaseInvoiceEditorSource({
    invoiceId: isEdit && open ? (invoiceId ?? null) : null,
    orderIds: !isEdit && open ? (purchaseOrderIds ?? null) : null,
  })

  const title = isEdit
    ? t('purchaseInvoices.editor.editTitle', { number: source?.numberLabel ?? '' })
    : t('purchaseInvoices.editor.createTitle')
  const target = isEdit && invoiceId !== undefined ? ({ mode: 'edit', invoiceId } as const) : ({ mode: 'create' } as const)

  const handleSaved = (invoice: PurchaseInvoice) => {
    onSaved?.(invoice)
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="xl" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3 text-left">
          <DialogTitle className="text-base">{title}</DialogTitle>
          <DialogDescription className="text-xs">
            {t(isEdit ? 'purchaseInvoices.editor.editDescription' : 'purchaseInvoices.editor.createDescription')}
          </DialogDescription>
        </DialogHeader>
        {open && source ? (
          <PurchaseInvoiceEditorForm
            source={source}
            target={target}
            onSaved={handleSaved}
            onCancel={() => onOpenChange(false)}
          />
        ) : (
          <div className="grid gap-2 px-4 py-4" aria-busy={isLoading}>
            {error ? (
              <p role="alert" className="text-sm text-destructive">
                {t('purchaseInvoices.editor.loadError')}
              </p>
            ) : (
              <>
                <span className="sr-only">{t('purchaseInvoices.editor.loading')}</span>
                <Skeleton className="h-24 w-full" />
                <Skeleton className="h-40 w-full" />
              </>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
