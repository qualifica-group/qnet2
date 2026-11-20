import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { forSelectKeys } from '@/features/for-select/query-keys'
import {
  createQuoteSystemStatus,
  quoteSystemStatusDetailQueryKey,
  updateQuoteSystemStatus,
} from '@/features/quote-system-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/quote-system-statuses/quote-system-status-form-payload'
import {
  buildQuoteSystemStatusSchema,
  type QuoteSystemStatusFormValues,
} from '@/features/quote-system-statuses/quote-system-status-schema'
import { QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE } from '@/features/quote-system-statuses/use-quote-system-status-options'
import type { QuoteSystemStatusDetail, QuoteSystemStatusFormMode } from '@/features/quote-system-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'behavior'] as const

interface UseQuoteSystemStatusFormArgs {
  mode: QuoteSystemStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quoteSystemStatus: QuoteSystemStatusDetail) => void
}

/**
 * Owns every non-render concern of the system status form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs the create form; edit backs the detail's in-place rows (diff PATCH).
 */
export function useQuoteSystemStatusForm({ mode, onSuccess }: UseQuoteSystemStatusFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)
  const isEdit = mode.type === 'edit'

  const schema = useMemo(() => buildQuoteSystemStatusSchema(t), [t])

  const defaultValues = useMemo<QuoteSystemStatusFormValues>(() => {
    if (mode.type === 'edit') {
      const { name, color, behavior } = mode.quoteSystemStatus
      return { name, color: color ?? '', behavior }
    }
    return { name: '', color: '', behavior: 'open' }
  }, [mode])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<QuoteSystemStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: QuoteSystemStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<QuoteSystemStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateQuoteSystemStatus(
          mode.quoteSystemStatus.id,
          buildUpdatePayload(values, mode.quoteSystemStatus),
        )
        queryClient.setQueryData(quoteSystemStatusDetailQueryKey(mode.quoteSystemStatus.id), saved)
        void queryClient.invalidateQueries({ queryKey: forSelectKeys.resource(QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE) })
        toast.success(t('quoteSystemStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createQuoteSystemStatus(buildCreatePayload(values))
      void queryClient.invalidateQueries({ queryKey: forSelectKeys.resource(QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE) })
      toast.success(t('quoteSystemStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(apiErrorMessage(error, t('quoteSystemStatuses.form.genericError'), i18n))
      }
    }
  }

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  }
}
