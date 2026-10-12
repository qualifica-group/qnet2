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

interface PurchaseClearPaymentDialogProps {
  /** Sequence of the installment whose payment is about to be cleared; `null` closes the dialog. */
  sequence: number | null
  isPending: boolean
  onClose: () => void
  onConfirm: () => void
}

/** Confirm step before clearing a payment (it may restore the previous schedule, spec 0211 D-6). */
export function PurchaseClearPaymentDialog({ sequence, isPending, onClose, onConfirm }: PurchaseClearPaymentDialogProps) {
  const { t } = useTranslation()

  return (
    <AlertDialog open={sequence !== null} onOpenChange={(open) => (open || isPending ? undefined : onClose())}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('purchaseInvoiceInstallments.clearPayment.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('purchaseInvoiceInstallments.clearPayment.description', { sequence: sequence ?? '' })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isPending}>{t('purchaseInvoiceInstallments.clearPayment.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={isPending}
            onClick={(event) => {
              event.preventDefault()
              onConfirm()
            }}
          >
            {t('purchaseInvoiceInstallments.clearPayment.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
