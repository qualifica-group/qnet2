import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createQuoteStatus,
  updateQuoteStatus,
} from '@/features/quote-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/quote-statuses/quote-status-form-payload'
import {
  buildCreateQuoteStatusSchema,
  buildUpdateQuoteStatusSchema,
  type CreateQuoteStatusFormValues,
  type UpdateQuoteStatusFormValues,
} from '@/features/quote-statuses/quote-status-schema'
import type {
  QuoteStatusDetail,
  QuoteStatusFormMode,
} from '@/features/quote-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'group'] as const

export type QuoteStatusFormValues = CreateQuoteStatusFormValues &
  UpdateQuoteStatusFormValues

interface UseQuoteStatusFormArgs {
  mode: QuoteStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quoteStatus: QuoteStatusDetail) => void
}

/**
 * Owns every non-render concern of `QuoteStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useQuoteStatusForm({ mode, onSuccess }: UseQuoteStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateQuoteStatusSchema(t) : buildCreateQuoteStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<QuoteStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.quoteStatus.name,
        color: mode.quoteStatus.color ?? '',
        group: mode.quoteStatus.group,
      }
    }
    return { name: '', color: '', group: 'open' }
  }, [mode])

  const form = useForm<QuoteStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: QuoteStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<QuoteStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateQuoteStatus(
          mode.quoteStatus.id,
          buildUpdatePayload(values, mode.quoteStatus),
        )
        queryClient.setQueryData(['quote-statuses', 'detail', mode.quoteStatus.id], saved)
        toast.success(t('quoteStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createQuoteStatus(buildCreatePayload(values))
      toast.success(t('quoteStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('quoteStatuses.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
