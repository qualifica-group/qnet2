import { OutboundEmailComposerDialog } from '@/features/outbound-emails/outbound-email-composer-dialog'
import type { InvoiceComposerTarget } from '@/features/invoices/use-invoice-email-flow'

interface InvoiceEmailComposerHostProps {
  target: InvoiceComposerTarget | null
  onClose: () => void
}

/** Mounts the shared composer on the draft the `email`/`remind` row actions just created (default recipient prefilled). */
export function InvoiceEmailComposerHost({ target, onClose }: InvoiceEmailComposerHostProps) {
  if (target === null) {
    return null
  }
  return (
    <OutboundEmailComposerDialog
      key={target.emailId}
      owner={{ type: 'invoices', id: target.invoiceId }}
      emailId={target.emailId}
      justCreated={target.justCreated}
      prefillDefaultTo
      open
      onOpenChange={(open) => (open ? undefined : onClose())}
    />
  )
}
