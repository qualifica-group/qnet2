import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ProformaNoteField } from '@/features/proforma-requests/proforma-note-field'
import { useProformaNoteForm } from '@/features/proforma-requests/use-proforma-note-form'
import type { ProformaSummary } from '@/features/proforma-requests/types'
import { useProformaSummary, useSendProformaRequest } from '@/features/work-orders/use-proforma-request-dialog'

interface ProformaRequestDialogProps {
  workOrderId: number
  workOrderCode: string
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Called after the requests were created, so the host can refresh its grid. */
  onSent: () => void
}

interface ProformaRequestFormProps {
  workOrderId: number
  title: string
  summary: ProformaSummary
  onClose: () => void
  onSent: () => void
}

/** Payment method line, last-request notice, note textarea and the two buttons. */
function ProformaRequestForm({ workOrderId, title, summary, onClose, onSent }: ProformaRequestFormProps) {
  const { t } = useTranslation()
  // The textarea starts from the dialog title, as the legacy screen did.
  const form = useProformaNoteForm(title)
  const send = useSendProformaRequest({ workOrderId, setError: form.setError, onSent })
  const isPending = summary.status === 'pending'
  const lastRequestedAt = formatDateTime(summary.last_requested_at)

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit((values) => send.mutate(values))} noValidate className="flex flex-col gap-3">
        <p className="text-sm">
          <span className="font-medium">{t('proformaRequests.dialog.paymentMethod')}:</span>{' '}
          {summary.payment_method?.name ?? t('proformaRequests.dialog.paymentMethodNone')}
        </p>
        {isPending && lastRequestedAt ? (
          <p role="status" className="rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground">
            {t('proformaRequests.dialog.lastRequest', { date: lastRequestedAt })}
          </p>
        ) : null}
        <ProformaNoteField control={form.control} />
        <DialogFooter className="gap-2 sm:gap-2">
          <Button type="button" variant="secondary" size="sm" onClick={onClose}>
            {t('proformaRequests.dialog.close')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending || send.isPending}>
            {t('proformaRequests.dialog.send')}
          </Button>
        </DialogFooter>
      </form>
    </Form>
  )
}

/**
 * "Richiesta emissione Proforma" modal (spec 0193 D-10): reads the work
 * order's summary, then lets the user send the note to Accounting. With a
 * pending request already on file the send button is disabled (D-4).
 */
export function ProformaRequestDialog({
  workOrderId,
  workOrderCode,
  open,
  onOpenChange,
  onSent,
}: ProformaRequestDialogProps) {
  const { t } = useTranslation()
  const { data: summary, isPending, isError, refetch } = useProformaSummary(workOrderId)
  const title = t('proformaRequests.dialog.title', { code: workOrderCode })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="sm">
        <DialogHeader className="text-left">
          <DialogTitle className="text-base">{title}</DialogTitle>
          <DialogDescription className="sr-only">{t('proformaRequests.dialog.description')}</DialogDescription>
        </DialogHeader>
        {isError ? (
          <div className="flex flex-col items-start gap-3">
            <p className="text-sm text-destructive" role="alert">
              {t('proformaRequests.dialog.loadError')}
            </p>
            <Button variant="secondary" size="sm" onClick={() => void refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : isPending ? (
          <div className="flex flex-col gap-3" aria-hidden="true">
            <Skeleton className="h-5 w-2/3" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : (
          <ProformaRequestForm
            workOrderId={workOrderId}
            title={title}
            summary={summary}
            onClose={() => onOpenChange(false)}
            onSent={onSent}
          />
        )}
      </DialogContent>
    </Dialog>
  )
}
