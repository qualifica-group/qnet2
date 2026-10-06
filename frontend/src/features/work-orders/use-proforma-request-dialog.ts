import { useMutation, useQuery } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import type { UseFormSetError } from 'react-hook-form'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createProformaRequests,
  fetchProformaSummary,
  proformaSummaryQueryKey,
} from '@/features/proforma-requests/api'
import type { ProformaNoteFormValues } from '@/features/proforma-requests/proforma-note-schema'
import type { ProformaSummary } from '@/features/proforma-requests/types'

/** Server message of a 409/422 envelope (`{ success:false, message }`), when it carries one. */
function serverMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const message = (error.response?.data as { message?: unknown } | undefined)?.message
  return typeof message === 'string' && message !== '' ? message : null
}

/** Loads the work order's proforma summary (state, last request date, offer payment method). */
export function useProformaSummary(workOrderId: number) {
  return useQuery<ProformaSummary>({
    queryKey: proformaSummaryQueryKey(workOrderId),
    queryFn: () => fetchProformaSummary(workOrderId),
  })
}

interface UseSendProformaRequestArgs {
  workOrderId: number
  setError: UseFormSetError<ProformaNoteFormValues>
  /** Called once the requests were created (the caller closes the dialog and refreshes the grid). */
  onSent: () => void
}

/**
 * Sends the request. Success: toast + `onSent`. A 422 on `note` lands on the
 * field; any other 409/422 shows the server's own message (duplicate pending
 * request, no billable lines) in a toast, since it is not field-bound.
 */
export function useSendProformaRequest({ workOrderId, setError, onSent }: UseSendProformaRequestArgs) {
  const { t } = useTranslation()

  return useMutation({
    mutationFn: (values: ProformaNoteFormValues) => createProformaRequests(workOrderId, values),
    onSuccess: () => {
      toast.success(t('proformaRequests.dialog.sent'))
      onSent()
    },
    onError: (error) => {
      const noteErrors = axios.isAxiosError(error)
        ? (error.response?.data as { errors?: { note?: string[] } } | undefined)?.errors?.note
        : undefined
      if (noteErrors && noteErrors.length > 0) {
        applyServerValidationErrors(error, setError, ['note'])
        return
      }
      const status = axios.isAxiosError(error) ? error.response?.status : undefined
      const isBusinessRejection = status === 409 || status === 422
      toast.error((isBusinessRejection ? serverMessage(error) : null) ?? t('proformaRequests.dialog.sendError'))
    },
  })
}
