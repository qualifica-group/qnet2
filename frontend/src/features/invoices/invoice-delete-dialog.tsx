import { useTranslation } from 'react-i18next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { useDeleteInvoiceFlow } from '@/features/invoices/use-delete-invoice-flow'

interface InvoiceDeleteDialogProps {
  /** Document to delete; `null` closes the dialog. */
  target: { id: number; numberLabel: string } | null
  onClose: () => void
  /** Called after a successful delete (the caller refreshes its grid). */
  onDeleted: () => void
}

/** Confirm then delete; a 409 (collected installments) surfaces as a toast and the dialog closes. */
export function InvoiceDeleteDialog({ target, onClose, onDeleted }: InvoiceDeleteDialogProps) {
  const { t } = useTranslation()
  const { confirm, isPending } = useDeleteInvoiceFlow(onDeleted, onClose)

  return (
    <AlertDialog open={target !== null} onOpenChange={(open) => (open || isPending ? undefined : onClose())}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('invoices.delete.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('invoices.delete.description', { number: target?.numberLabel ?? '' })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isPending}>{t('invoices.delete.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={isPending}
            onClick={(event) => {
              event.preventDefault()
              if (target) {
                confirm(target.id)
              }
            }}
          >
            {t('invoices.delete.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
