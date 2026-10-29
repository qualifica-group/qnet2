import { useCallback, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useClearCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment } from '@/features/invoices/types'

const CONFLICT_STATUS = 409

/** Dialog targets + confirmed "Annulla incasso" mutation shared by every surface listing installments. */
export function useInvoiceCollectionActions(onChanged?: () => void) {
  const { t, i18n } = useTranslation()
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
          error.response?.status === CONFLICT_STATUS
            ? t('invoices.detail.clearCollectionConflict')
            : apiErrorMessage(error, t('invoices.collection.genericError'), i18n),
        ),
      onSettled: () => setClearTarget(null),
    })
  }, [clearCollection, clearTarget, onChanged, t, i18n])

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
