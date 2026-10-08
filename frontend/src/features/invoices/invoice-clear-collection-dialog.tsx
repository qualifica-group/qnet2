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
import type { InvoiceInstallment } from '@/features/invoices/types'

interface InvoiceClearCollectionDialogProps {
  /** Installment whose collection is about to be cleared; `null` closes the dialog. */
  target: InvoiceInstallment | null
  isPending: boolean
  onClose: () => void
  onConfirm: () => void
}

/** Confirm step before clearing a collection (it may restore the previous schedule, spec 0196 D-7). */
export function InvoiceClearCollectionDialog({ target, isPending, onClose, onConfirm }: InvoiceClearCollectionDialogProps) {
  const { t } = useTranslation()

  return (
    <AlertDialog open={target !== null} onOpenChange={(open) => (open || isPending ? undefined : onClose())}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('invoices.detail.clearDialog.title')}</AlertDialogTitle>
          <AlertDialogDescription>
            {t('invoices.detail.clearDialog.description', { sequence: target?.sequence ?? '' })}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={isPending}>{t('invoices.detail.clearDialog.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            disabled={isPending}
            onClick={(event) => {
              event.preventDefault()
              onConfirm()
            }}
          >
            {t('invoices.detail.clearDialog.confirm')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
