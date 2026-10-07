import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useClearCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment } from '@/features/invoices/types'

const CONFLICT_STATUS = 409

/** Dialog targets + confirmed "Annulla incasso" mutation shared by every surface listing installments. */
export function useInvoiceCollectionActions(onChanged?: () => void) {
  const { t } = useTranslation()
  const [collectTarget, setCollectTarget] = useState<InvoiceInstallment | null>(null)
  const [clearTarget, setClearTarget] = useState<InvoiceInstallment | null>(null)
  const clearMutation = useClearCollection()
  const { mutate: clearCollection, isPending: isClearing } = clearMutation

  const closeCollect = useCallback(() => setCollectTarget(null), [])
  const closeClear = useCallback(() => setClearTarget(null), [])

  const confirmClear = useCallback(() => {
    if (clearTarget === null) {
      return
    }
    clearCollection(clearTarget.id, {
      onSuccess: () => {
        toast.success(t('invoices.detail.collectionCleared'))
        onChanged?.()
      },
      onError: (error) =>
        toast.error(
          t(
            error.response?.status === CONFLICT_STATUS
              ? 'invoices.detail.clearCollectionConflict'
              : 'invoices.collection.genericError',
          ),
        ),
      onSettled: () => setClearTarget(null),
    })
  }, [clearCollection, clearTarget, onChanged, t])

  return {
    collectTarget,
    openCollect: setCollectTarget,
    closeCollect,
    clearTarget,
    requestClear: setClearTarget,
    closeClear,
    confirmClear,
    isClearing,
    busyInstallmentId: isClearing ? (clearTarget?.id ?? null) : null,
  }
}
