import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useClearCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment } from '@/features/invoices/types'

/** Dialog target + "Annulla incasso" mutation shared by every surface listing installments. */
export function useInvoiceCollectionActions(onChanged?: () => void) {
  const { t } = useTranslation()
  const [collectTarget, setCollectTarget] = useState<InvoiceInstallment | null>(null)
  const [busyInstallmentId, setBusyInstallmentId] = useState<number | null>(null)
  const clearMutation = useClearCollection()
  const { mutate: clearCollection } = clearMutation

  const closeCollect = useCallback(() => setCollectTarget(null), [])

  const clear = useCallback(
    (installment: InvoiceInstallment) => {
      setBusyInstallmentId(installment.id)
      clearCollection(installment.id, {
        onSuccess: () => {
          toast.success(t('invoices.detail.collectionCleared'))
          onChanged?.()
        },
        onError: () => toast.error(t('invoices.collection.genericError')),
        onSettled: () => setBusyInstallmentId(null),
      })
    },
    [clearCollection, onChanged, t],
  )

  return {
    collectTarget,
    openCollect: setCollectTarget,
    closeCollect,
    clear,
    busyInstallmentId,
  }
}
