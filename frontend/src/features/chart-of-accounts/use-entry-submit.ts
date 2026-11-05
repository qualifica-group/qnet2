import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { chartOfAccountsKeys } from '@/features/chart-of-accounts/query-keys'
import { apiErrorMessage } from '@/lib/forbidden-message'

interface UseEntrySubmitArgs<TValues extends FieldValues> {
  form: UseFormReturn<TValues>
  /** Form fields a 422 can be mapped onto. */
  errorFields: Path<TValues>[]
  save: (values: TValues) => Promise<unknown>
  successMessage: string
  onSaved: () => void
}

/**
 * Shared submit of every chart-of-accounts sheet: runs the write, refreshes the
 * tree, and on failure keeps the form open (422 on the fields, anything else as
 * a toast).
 */
export function useEntrySubmit<TValues extends FieldValues>({
  form,
  errorFields,
  save,
  successMessage,
  onSaved,
}: UseEntrySubmitArgs<TValues>) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  const mutation = useMutation({
    mutationFn: save,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: chartOfAccountsKeys.tree() })
      toast.success(successMessage)
      onSaved()
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        toast.error(apiErrorMessage(error, t('chartOfAccounts.genericError'), i18n))
      }
    },
  })

  return {
    onSubmit: form.handleSubmit((values) => mutation.mutate(values)),
    isPending: mutation.isPending,
  }
}
