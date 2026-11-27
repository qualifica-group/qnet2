import axios from 'axios'
import type { i18n as I18nInstance, TFunction } from 'i18next'
import { toast } from 'sonner'
import { notifyFormErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'

const HTTP_UNPROCESSABLE = 422

/** The reasons of a 422 (e.g. spec 0323 D-2, a status that would not raise the progress), empty otherwise. */
function validationMessages(error: unknown): string[] {
  if (!axios.isAxiosError(error) || error.response?.status !== HTTP_UNPROCESSABLE) {
    return []
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined

  return Object.values(errors ?? {}).flatMap((messages) => messages?.[0] ?? [])
}

/**
 * A refused PATCH of one work order field outside its form (the header's
 * "Stato lavorazione", the team popup): a foreground toast with the server's
 * reasons, else the 403 sentence or the generic failure.
 */
export function notifyWorkOrderSaveError(error: unknown, t: TFunction, i18n: I18nInstance): void {
  const messages = validationMessages(error)
  if (messages.length > 0) {
    notifyFormErrors(messages)
    return
  }
  toast.error(apiErrorMessage(error, t('workOrders.form.genericError'), i18n))
}
