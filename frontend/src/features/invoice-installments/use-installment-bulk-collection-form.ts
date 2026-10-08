import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios, { type AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { bulkCollectInstallments, installmentKeys } from '@/features/invoice-installments/api'
import {
  buildBulkCollectionPayload,
  buildBulkCollectionSchema,
  collectedTotal,
  selectedTotal,
  type BulkCollectionFormValues,
  type BulkCollectionTarget,
} from '@/features/invoice-installments/installment-bulk-collection'
import type { BulkCollectionPayload, BulkCollectionResult } from '@/features/invoice-installments/types'
import { formatEuro, todayIso } from '@/features/invoices/invoice-format'
import { invoiceKeys } from '@/features/invoices/api'

const VALIDATION_STATUS = 422
const ROW_ERROR = /^items\.(\d+)\.(installment_id|collected_amount)$/

/**
 * Form + submit of the bulk collection (spec 0198): one collection date, one
 * optional amount per selected installment, the running total for the footer.
 * A server 422 is mapped back onto the dialog row it belongs to.
 */
export function useInstallmentBulkCollectionForm(targets: readonly BulkCollectionTarget[], onDone: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildBulkCollectionSchema(t, targets), [t, targets])

  const form = useForm<BulkCollectionFormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: { collected_at: todayIso(), amounts: targets.map(() => '') },
  })

  const amounts = useWatch({ control: form.control, name: 'amounts' })
  const totalCollected = collectedTotal(amounts)

  const mutation = useMutation<BulkCollectionResult, AxiosError, BulkCollectionPayload>({
    mutationFn: bulkCollectInstallments,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: installmentKeys.all })
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
    },
  })

  const submit = form.handleSubmit((values) => {
    const { payload, rowIndexes } = buildBulkCollectionPayload(values, targets)
    if (payload.items.length === 0) {
      return
    }
    mutation.mutate(payload, {
      onSuccess: (result) => {
        toast.success(
          t('invoiceInstallments.bulkCollection.saved', {
            count: result.collected_count,
            residuals: result.residual_count,
          }),
        )
        onDone()
      },
      onError: (error) => {
        if (!mapServerErrors(error, rowIndexes)) {
          toast.error(t('invoiceInstallments.bulkCollection.genericError'))
        }
      },
    })
  })

  // Every server message is replaced by its localized twin: the API answers in English.
  function mapServerErrors(error: unknown, rowIndexes: number[]): boolean {
    if (!axios.isAxiosError(error) || error.response?.status !== VALIDATION_STATUS) {
      return false
    }
    const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
    let mapped = false
    for (const key of Object.keys(errors)) {
      const match = ROW_ERROR.exec(key)
      const rowIndex = match ? rowIndexes[Number(match[1])] : undefined
      if (match && rowIndex !== undefined) {
        const target = targets[rowIndex]
        form.setError(`amounts.${rowIndex}`, {
          message:
            match[2] === 'installment_id'
              ? t('invoiceInstallments.bulkCollection.errors.alreadyCollected')
              : t('invoiceInstallments.bulkCollection.errors.amountExceeds', { amount: formatEuro(target?.amount) }),
        })
        mapped = true
      } else if (key === 'items') {
        toast.error(t('invoiceInstallments.bulkCollection.errors.mixedCustomers'))
        mapped = true
      } else if (key === 'collected_at') {
        form.setError('collected_at', { message: t('invoiceInstallments.bulkCollection.errors.dateRequired') })
        mapped = true
      }
    }
    return mapped
  }

  const hasErrors = Object.keys(form.formState.errors).length > 0

  return {
    form,
    submit,
    isPending: mutation.isPending,
    totalCollected,
    totalSelected: selectedTotal(targets),
    canSave: totalCollected > 0 && !hasErrors && !mutation.isPending,
  }
}
