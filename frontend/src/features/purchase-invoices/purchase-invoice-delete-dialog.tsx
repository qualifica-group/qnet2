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
import { useDeletePurchaseInvoiceFlow } from '@/features/purchase-invoices/use-delete-purchase-invoice-flow'
import type { PurchaseInvoiceDeleteTarget } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'

interface PurchaseInvoiceDeleteDialogProps {
  /** Document to delete; `null` closes the dialog. */
  target: PurchaseInvoiceDeleteTarget | null
  onClose: () => void
  /** Called after a successful delete (the caller refreshes its grid). */
  onDeleted: () => void
}

/** Confirm then delete; a 409 (registered invoice or payments) surfaces as a toast and the dialog closes. */
export function PurchaseInvoiceDeleteDialog({ target, onClose, onDeleted }: PurchaseInvoiceDeleteDialogProps) {
  const { t } = useTranslation()
  const { confirm, isPending } = useDeletePurchaseInvoiceFlow(onDeleted, onClose)

  return (
    <AlertDialog open={target !== null} onOpenChange={(open) => (open || isPending ? undefined : onClose())}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('purchaseInvoiceViews.delete.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('purchaseInvoiceViews.delete.description', { number: target?.numberLabel ?? '' })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isPending}>{t('purchaseInvoiceViews.delete.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={isPending}
            onClick={(event) => {
              event.preventDefault()
              if (target) {
                confirm(target.id)
              }
            }}
          >
            {t('purchaseInvoiceViews.delete.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
