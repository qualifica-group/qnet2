import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import {
  createQuoteWorkflow,
  quoteWorkflowDetailQueryKey,
  updateQuoteWorkflow,
} from '@/features/quote-workflows/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/quote-workflows/quote-workflow-form-payload'
import {
  buildCreateQuoteWorkflowSchema,
  buildUpdateQuoteWorkflowSchema,
  type CreateQuoteWorkflowFormValues,
} from '@/features/quote-workflows/quote-workflow-schema'
import { initialSystemStatusRows, statusRowsFromDetail } from '@/features/quote-workflows/quote-status-rows'
import type { QuoteWorkflowDetail, QuoteWorkflowFormMode } from '@/features/quote-workflows/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'is_active', 'criteria', 'statuses'] as const

/** Collections whose server errors arrive per row (`criteria.0.field`) but show once, on the collection. */
const NESTED_ERROR_ROOTS = ['criteria', 'statuses'] as const

export type QuoteWorkflowFormValues = CreateQuoteWorkflowFormValues

/** A fresh, still-empty criteria row. */
const EMPTY_CRITERION_ROW = { field: null, value_id: null }

/** Surfaces the first per-row message of a collection when the server sent none for the collection itself. */
function applyNestedServerErrors(
  error: unknown,
  setError: UseFormSetError<QuoteWorkflowFormValues>,
): void {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return
  }
  const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
  for (const root of NESTED_ERROR_ROOTS) {
    const key = Object.keys(errors).find((candidate) => candidate.startsWith(`${root}.`))
    if (key && errors[root] === undefined && errors[key]?.[0]) {
      setError(root, { message: errors[key][0] })
    }
  }
}

interface UseQuoteWorkflowFormArgs {
  mode: QuoteWorkflowFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (quoteWorkflow: QuoteWorkflowDetail) => void
}

/**
 * Owns every non-render concern of the opportunity workflow form: RHF/Zod
 * wiring for `name`/`is_active`, `criteria` (a field array) and `statuses`
 * (a plain value edited by the `<SortableList>` editor), server 422 mapping
 * and the create/update submit. Create backs `QuoteWorkflowForm`; edit backs
 * the detail's in-place rows (diff PATCH). The component stays UI-only; this
 * hook is the orchestration point (`onSubmit`).
 */
export function useQuoteWorkflowForm({ mode, onSuccess }: UseQuoteWorkflowFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateQuoteWorkflowSchema(t) : buildCreateQuoteWorkflowSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<QuoteWorkflowFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.quoteWorkflow.name,
        is_active: mode.quoteWorkflow.is_active,
        criteria: mode.quoteWorkflow.criteria.map((criterion) => ({
          field: criterion.field,
          value_id: criterion.value_id,
        })),
        statuses: statusRowsFromDetail(mode.quoteWorkflow),
      }
    }
    return {
      name: '',
      is_active: true,
      criteria: [{ ...EMPTY_CRITERION_ROW }],
      statuses: initialSystemStatusRows(t),
    }
  }, [mode, t])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<QuoteWorkflowFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: QuoteWorkflowFormValues) => {
    setServerError(null)
    const errorFields: Path<QuoteWorkflowFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateQuoteWorkflow(
          mode.quoteWorkflow.id,
          buildUpdatePayload(values, mode.quoteWorkflow),
        )
        queryClient.setQueryData(quoteWorkflowDetailQueryKey(mode.quoteWorkflow.id), saved)
        toast.success(t('quoteWorkflows.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createQuoteWorkflow(buildCreatePayload(values))
      toast.success(t('quoteWorkflows.form.created'))
      onSuccess(created)
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, errorFields)
      applyNestedServerErrors(error, form.setError)
      if (!handled) {
        setServerError(apiErrorMessage(error, t('quoteWorkflows.form.genericError'), i18n))
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
