import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { importPayrollCosts } from '@/features/payroll-costs/api'
import type { PayrollImportResult } from '@/features/payroll-costs/types'
import { apiErrorMessage } from '@/lib/forbidden-message'

/** First server message on the `file` field (422), else the envelope message, else the fallback. */
function fileErrorMessage(error: unknown, fallback: string): string {
  if (!axios.isAxiosError<Partial<ApiErrorResponse>>(error)) return fallback
  return error.response?.data?.errors?.file?.[0] ?? error.response?.data?.message ?? fallback
}

/** Two-step import: choosing a file previews it (dry run), confirming applies it all-or-nothing. */
export function usePayrollImport(onApplied: () => void) {
  const { t, i18n } = useTranslation()
  const [file, setFile] = useState<File | null>(null)

  const preview = useMutation({ mutationFn: (selected: File) => importPayrollCosts(selected, true) })
  const apply = useMutation({
    mutationFn: (selected: File) => importPayrollCosts(selected, false),
    onSuccess: (outcome) => {
      if (!outcome.applied) return
      toast.success(t('payrollCosts.import.done'))
      onApplied()
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('payrollCosts.import.error'), i18n)),
  })

  // The apply step answers like the preview when errors surfaced in the meantime.
  const result: PayrollImportResult | undefined = apply.data && !apply.data.applied ? apply.data : preview.data
  const requestError = preview.isError ? fileErrorMessage(preview.error, t('payrollCosts.import.error')) : null

  const selectFile = (selected: File | null) => {
    setFile(selected)
    apply.reset()
    if (selected) preview.mutate(selected)
    else preview.reset()
  }

  return {
    selectFile,
    result,
    requestError,
    isPreviewing: preview.isPending,
    isApplying: apply.isPending,
    canConfirm: file !== null && result !== undefined && result.errors.length === 0 && !apply.isPending,
    confirm: () => {
      if (file) apply.mutate(file)
    },
  }
}
