import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { InvoiceEditorForm } from '@/features/invoices/invoice-editor-form'
import { useInvoiceEditorSource } from '@/features/invoices/invoice-editor-source'
import type { Invoice } from '@/features/invoices/types'

export interface InvoiceEditorDialogProps {
  /** The proforma request being invoiced. */
  proformaRequestId: number
  open: boolean
  onClose: () => void
  onSaved: (invoice: Invoice) => void
}

/**
 * Issue modal of an active invoice from a proforma request (spec 0194). Loads
 * the draft, then mounts the form so its defaults are the loaded data. The
 * form is unmounted on close: reopening always starts from a fresh read. An
 * issued document is edited on its record (spec 0263).
 */
export function InvoiceEditorDialog({ proformaRequestId, open, onClose, onSaved }: InvoiceEditorDialogProps) {
  const { t } = useTranslation()
  const { source, isLoading, error } = useInvoiceEditorSource(open ? proformaRequestId : null)

  const title = t('invoiceEditor.createTitle', { code: source?.context.workOrderCode ?? '' })
  const description = t('invoiceEditor.createDescription')

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="xl" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3 text-left">
          <DialogTitle className="text-base">{title}</DialogTitle>
          <DialogDescription className="text-xs">{description}</DialogDescription>
        </DialogHeader>
        {source ? (
          <InvoiceEditorForm source={source} proformaRequestId={proformaRequestId} onSaved={onSaved} onCancel={onClose} />
        ) : (
          <div className="grid gap-2 px-4 py-4" aria-busy={isLoading}>
            {error ? (
              <p role="alert" className="text-sm text-destructive">
                {t(error.response?.status === 409 ? 'invoiceEditor.alreadyInvoiced' : 'invoiceEditor.loadError')}
              </p>
            ) : (
              <>
                <span className="sr-only">{t('invoiceEditor.loading')}</span>
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
