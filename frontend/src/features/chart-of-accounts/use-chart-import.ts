import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { importChartOfAccounts } from '@/features/chart-of-accounts/api'
import { chartOfAccountsKeys } from '@/features/chart-of-accounts/query-keys'
import type { ChartImportResult } from '@/features/chart-of-accounts/types'
import { apiErrorMessage } from '@/lib/forbidden-message'

/** First server message on the `file` field (422), else the envelope message, else the fallback. */
function fileErrorMessage(error: unknown, fallback: string): string {
  if (!axios.isAxiosError<Partial<ApiErrorResponse>>(error)) return fallback
  return error.response?.data?.errors?.file?.[0] ?? error.response?.data?.message ?? fallback
}

/** Two-step import: choosing a file previews it (dry run), confirming applies it all-or-nothing. */
export function useChartImport(onDone: () => void) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [file, setFile] = useState<File | null>(null)

  const preview = useMutation({ mutationFn: (selected: File) => importChartOfAccounts(selected, true) })
  const apply = useMutation({
    mutationFn: (selected: File) => importChartOfAccounts(selected, false),
    onSuccess: async (outcome) => {
      if (!outcome.applied) return
      await queryClient.invalidateQueries({ queryKey: chartOfAccountsKeys.tree() })
      toast.success(t('chartOfAccounts.import.done'))
      onDone()
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('chartOfAccounts.import.error'), i18n)),
  })

  // The apply step answers like the preview when errors surfaced in the meantime.
  const result: ChartImportResult | undefined = apply.data && !apply.data.applied ? apply.data : preview.data
  const requestError = preview.isError ? fileErrorMessage(preview.error, t('chartOfAccounts.import.error')) : null

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
