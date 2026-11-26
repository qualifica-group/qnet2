import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { importPayslips } from '@/features/purchase-invoices/payslips/api'
import type { PayslipImportRequest, PayslipImportResult } from '@/features/purchase-invoices/payslips/types'
import { apiErrorMessage } from '@/lib/forbidden-message'

/** First server message on the `file` or `payment_date` field (422), else the envelope message, else the fallback. */
function fieldErrorMessage(error: unknown, fallback: string): string {
  if (!axios.isAxiosError<Partial<ApiErrorResponse>>(error)) return fallback
  const data = error.response?.data
  return data?.errors?.file?.[0] ?? data?.errors?.payment_date?.[0] ?? data?.message ?? fallback
}

/** Two-step import: file + payment date preview the file (dry run), confirming applies it all-or-nothing. */
export function usePayslipImport(onApplied: () => void) {
  const { t, i18n } = useTranslation()
  const [file, setFile] = useState<File | null>(null)
  const [paymentDate, setPaymentDate] = useState('')

  const preview = useMutation({ mutationFn: (request: PayslipImportRequest) => importPayslips(request, true) })
  const apply = useMutation({
    mutationFn: (request: PayslipImportRequest) => importPayslips(request, false),
    onSuccess: (outcome) => {
      if (!outcome.applied) return
      toast.success(t('purchaseInvoiceViews.payslipImport.done'))
      onApplied()
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('purchaseInvoiceViews.payslipImport.error'), i18n)),
  })

  // The apply step answers like the preview when errors surfaced in the meantime.
  const result: PayslipImportResult | undefined = apply.data && !apply.data.applied ? apply.data : preview.data
  const requestError = preview.isError
    ? fieldErrorMessage(preview.error, t('purchaseInvoiceViews.payslipImport.error'))
    : null
  const request: PayslipImportRequest | null = file && paymentDate ? { file, paymentDate } : null

  const refreshPreview = (next: PayslipImportRequest | null) => {
    apply.reset()
    if (next) preview.mutate(next)
    else preview.reset()
  }

  return {
    paymentDate,
    selectFile: (selected: File | null) => {
      setFile(selected)
      refreshPreview(selected && paymentDate ? { file: selected, paymentDate } : null)
    },
    selectPaymentDate: (date: string) => {
      setPaymentDate(date)
      refreshPreview(file && date ? { file, paymentDate: date } : null)
    },
    result,
    requestError,
    isPreviewing: preview.isPending,
    isApplying: apply.isPending,
    canConfirm: request !== null && result !== undefined && result.errors.length === 0 && !apply.isPending,
    confirm: () => {
      if (request) apply.mutate(request)
    },
  }
}
