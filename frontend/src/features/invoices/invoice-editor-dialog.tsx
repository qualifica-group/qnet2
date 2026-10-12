import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { InvoiceEditorForm } from '@/features/invoices/invoice-editor-form'
import { useInvoiceEditorSource } from '@/features/invoices/invoice-editor-source'
import type { Invoice } from '@/features/invoices/types'

export interface InvoiceEditorDialogProps {
  proformaRequestId: number
  open: boolean
  onClose: () => void
  onSaved: (invoice: Invoice) => void
}

/**
 * Issue modal of an active invoice from a proforma request (spec 0194),
 * shaped like the document's detail (spec 0224 D-5). Editing an issued
 * document happens in place on its detail. Loads the draft, then mounts the
 * form so its defaults are the loaded data; unmounted on close, so reopening
 * always starts from a fresh read.
 */
export function InvoiceEditorDialog({ proformaRequestId, open, onClose, onSaved }: InvoiceEditorDialogProps) {
  const { t } = useTranslation()
  const { source, isLoading, error } = useInvoiceEditorSource(open ? proformaRequestId : null)
  // The form fills it with its guarded close (asks before dropping a changed draft).
  const closeRef = useRef<(() => void) | null>(null)
  const requestClose = () => (closeRef.current ? closeRef.current() : onClose())
  const title = t('invoiceEditor.createTitle', { code: source?.context.workOrderCode ?? '' })

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : requestClose())}>
      <DialogContent size="xl" className="gap-0 p-0">
        <DialogHeader className="flex-row items-center gap-3 border-b bg-surface px-4 py-3 text-left">
          <DetailMonogram name={title} icon={<FileText />} />
          <div className="flex min-w-0 flex-col gap-0.5">
            <DialogTitle className="truncate text-base">{title}</DialogTitle>
            <DialogDescription className="text-xs">{t('invoiceEditor.createDescription')}</DialogDescription>
          </div>
        </DialogHeader>
        {source ? (
          <InvoiceEditorForm
            source={source}
            proformaRequestId={proformaRequestId}
            onSaved={onSaved}
            onCancel={onClose}
            closeRef={closeRef}
          />
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
