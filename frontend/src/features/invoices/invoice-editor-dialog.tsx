import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { InvoiceEditorForm } from '@/features/invoices/invoice-editor-form'
import { useInvoiceEditorSource } from '@/features/invoices/invoice-editor-source'
import type { Invoice } from '@/features/invoices/types'
import type { InvoiceEditorTarget } from '@/features/invoices/use-invoice-editor-form'

export type InvoiceEditorDialogProps = {
  open: boolean
  onClose: () => void
  onSaved: (invoice: Invoice) => void
} & InvoiceEditorTarget

/**
 * Issue (create from a proforma request) / edit modal of an active invoice
 * (spec 0194). Loads the draft or the document, then mounts the form so its
 * defaults are the loaded data. The form is unmounted on close: reopening
 * always starts from a fresh read.
 */
export function InvoiceEditorDialog(props: InvoiceEditorDialogProps) {
  const { open, onClose, onSaved } = props
  const { t } = useTranslation()
  const target: InvoiceEditorTarget =
    props.mode === 'create'
      ? { mode: 'create', proformaRequestId: props.proformaRequestId }
      : { mode: 'edit', invoiceId: props.invoiceId }
  const { source, isLoading, error } = useInvoiceEditorSource({
    proformaRequestId: target.mode === 'create' && open ? target.proformaRequestId : null,
    invoiceId: target.mode === 'edit' && open ? target.invoiceId : null,
  })

  const title =
    target.mode === 'create'
      ? t('invoiceEditor.createTitle', { code: source?.context.workOrderCode ?? '' })
      : t('invoiceEditor.editTitle', { number: source?.numberLabel ?? '' })
  const description = target.mode === 'create' ? t('invoiceEditor.createDescription') : t('invoiceEditor.editDescription')

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="xl" className="gap-0 p-0">
        <DialogHeader className="border-b bg-surface px-4 py-3 text-left">
          <DialogTitle className="text-base">{title}</DialogTitle>
          <DialogDescription className="text-xs">{description}</DialogDescription>
        </DialogHeader>
        {source ? (
          <InvoiceEditorForm source={source} target={target} onSaved={onSaved} onCancel={onClose} />
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
