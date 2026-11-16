import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { FieldPath, UseFormSetError } from 'react-hook-form'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { transferQuoteRegistry, type QuoteRegistryTransferPayload } from '@/features/quotes/registry-transfer/api'
import type { QuoteRegistryTransferFormValues } from '@/features/quotes/registry-transfer/schema'

const ATTRIBUTE_ERROR_PREFIX = 'attribute_values.'

/** The 422 keys the dialog can show on a field; anything else is a toast. */
function isFormField(key: string): key is FieldPath<QuoteRegistryTransferFormValues> {
  return key === 'registry_id' || key.startsWith(ATTRIBUTE_ERROR_PREFIX)
}

/**
 * Maps a 422 onto the dialog's fields. Returns false when no error landed on
 * a field (e.g. "offer under contract", reported on `registry_id`, does land;
 * a 403 does not), so the caller falls back to a toast.
 */
function applyFieldErrors(error: unknown, setError: UseFormSetError<QuoteRegistryTransferFormValues>): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }

  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  const fieldKeys = Object.keys(errors).filter(isFormField)
  fieldKeys.forEach((key) => setError(key, { type: 'server', message: errors[key][0] }))

  return fieldKeys.length > 0
}

/**
 * The mutation behind "Trasferisci ad altra anagrafica" (spec 0404, part B),
 * shared by the offer detail and the Gestione Richieste / Iscritti panel:
 * only the endpoint's base path differs.
 */
export function useQuoteRegistryTransfer(basePath: string, quoteId: number, onTransferred: () => void) {
  const { t, i18n } = useTranslation()

  const mutation = useMutation({
    mutationFn: (payload: QuoteRegistryTransferPayload) => transferQuoteRegistry(basePath, quoteId, payload),
  })

  const submit = async (
    values: QuoteRegistryTransferFormValues,
    setError: UseFormSetError<QuoteRegistryTransferFormValues>,
  ): Promise<void> => {
    try {
      await mutation.mutateAsync({ registry_id: values.registry_id as number, attribute_values: values.attribute_values })
      toast.success(t('quotes.registryTransfer.success'))
      onTransferred()
    } catch (error) {
      if (!applyFieldErrors(error, setError)) {
        toast.error(apiErrorMessage(error, t('quotes.registryTransfer.errors.generic'), i18n))
      }
    }
  }

  return { submit, isPending: mutation.isPending }
}
