import { useMemo } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useLinesRecordInlineEdit } from '@/components/record-form/use-lines-record-inline-edit'
import { purchaseOrderKeys, updatePurchaseOrder } from '@/features/purchase-orders/api'
import {
  buildOrderHeaderPatch,
  buildOrderLinesPayload,
  toOrderFormValues,
} from '@/features/purchase-orders/purchase-order-payload'
import type { PurchaseOrderFormValues } from '@/features/purchase-orders/purchase-order-schema'
import { applyOrderServerErrors } from '@/features/purchase-orders/purchase-order-server-errors'
import type { PurchaseOrder, PurchaseOrderPayload } from '@/features/purchase-orders/types'
import { usePurchaseOrderFormState } from '@/features/purchase-orders/use-purchase-order-form'
import { isConflict, serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/**
 * Drives the ODA detail's in-place editing (spec 0257 D-6/D-7) on the shared
 * `useLinesRecordInlineEdit`: one editor at a time, a header row or a line,
 * each save a PATCH of only what that editor changed.
 */
export function usePurchaseOrderInlineEdit(order: PurchaseOrder, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const persisted = useMemo(() => toOrderFormValues(order), [order])
  const state = usePurchaseOrderFormState(order)

  const editing = useLinesRecordInlineEdit<PurchaseOrderFormValues, PurchaseOrderPayload, PurchaseOrder>({
    form: state.form,
    persisted,
    addLine: state.addLine,
    headerPatch: buildOrderHeaderPatch,
    linesPatch: (lines) => ({ lines: buildOrderLinesPayload(lines) }),
    save: (payload) => updatePurchaseOrder(order.id, payload),
    onSaved: (saved) => {
      queryClient.setQueryData(purchaseOrderKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: purchaseOrderKeys.all })
      toast.success(t('purchaseOrderForm.messages.updated'))
      onChanged?.()
      return toOrderFormValues(saved)
    },
    applyServerErrors: applyOrderServerErrors,
    errorMessage: (error) =>
      serverMessage(error) ??
      t(isConflict(error) ? 'purchaseOrderForm.messages.conflict' : 'purchaseOrderForm.messages.saveError'),
  })

  return { ...state, ...editing }
}

/** Everything the detail's editable sections read: the form state, the inline state and the line actions. */
export type PurchaseOrderDetailEditor = ReturnType<typeof usePurchaseOrderInlineEdit>
