import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useDeleteInvoice } from '@/features/invoices/use-invoice-queries'

const HTTP_CONFLICT = 409

/** Delete mutation with the user-facing outcomes: success, 409 (has collections), generic error. */
export function useDeleteInvoiceFlow(onDeleted: () => void, onSettled: () => void) {
  const { t, i18n } = useTranslation()
  const { mutate, isPending } = useDeleteInvoice()

  const confirm = useCallback(
    (id: number) =>
      mutate(id, {
        onSuccess: () => {
          toast.success(t('invoices.delete.deleted'))
          onDeleted()
        },
        onError: (error) => {
          const conflict = axios.isAxiosError(error) && error.response?.status === HTTP_CONFLICT
          toast.error(
            conflict
              ? t('invoices.delete.hasCollections')
              : apiErrorMessage(error, t('invoices.delete.genericError'), i18n),
          )
        },
        onSettled,
      }),
    [mutate, onDeleted, onSettled, t, i18n],
  )

  return { confirm, isPending }
}
