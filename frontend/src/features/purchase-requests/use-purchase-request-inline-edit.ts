import { useMemo } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useLinesRecordInlineEdit } from '@/components/record-form/use-lines-record-inline-edit'
import { purchaseRequestKeys, updatePurchaseRequest } from '@/features/purchase-requests/api'
import {
  buildHeaderPatch,
  buildLinesPayload,
  toFormValues,
} from '@/features/purchase-requests/purchase-request-payload'
import {
  applyServerErrors,
  isConflict,
  serverMessage,
} from '@/features/purchase-requests/purchase-request-server-errors'
import { usePurchaseRequestFormState } from '@/features/purchase-requests/use-purchase-request-form'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import type { PurchaseRequest, PurchaseRequestPayload } from '@/features/purchase-requests/types'

/** Rows whose editor shows more than their own field (spec 0256 D-6). */
const EDITOR_EXTRA_FIELDS: Record<string, string[]> = {
  company_id: ['company_site_id'],
  requester_id: ['function_manager_id'],
  business_function_id: ['function_manager_id'],
}

/**
 * Drives the RDA detail's in-place editing (spec 0256 D-6/D-7) on the shared
 * `useLinesRecordInlineEdit`: one editor at a time, a header row or a line,
 * each save a PATCH of only what that editor changed.
 */
export function usePurchaseRequestInlineEdit(request: PurchaseRequest, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const persisted = useMemo(() => toFormValues(request), [request])
  const state = usePurchaseRequestFormState({ request, requesterId: request.requester.id })

  const editing = useLinesRecordInlineEdit<PurchaseRequestFormValues, PurchaseRequestPayload, PurchaseRequest>({
    form: state.form,
    persisted,
    addLine: state.addLine,
    headerPatch: buildHeaderPatch,
    linesPatch: (lines) => ({ lines: buildLinesPayload(lines) }),
    save: (payload) => updatePurchaseRequest(request.id, payload),
    onSaved: (saved) => {
      queryClient.setQueryData(purchaseRequestKeys.detail(saved.id), saved)
      toast.success(t('purchaseRequests.messages.updated'))
      onChanged?.()
      return toFormValues(saved)
    },
    applyServerErrors,
    errorMessage: (error) =>
      serverMessage(error) ??
      t(isConflict(error) ? 'purchaseRequests.messages.conflict' : 'purchaseRequests.messages.saveError'),
    extraFields: EDITOR_EXTRA_FIELDS,
  })

  return { ...state, ...editing }
}

/** Everything the detail's editable sections read: the form state, the inline state and the line actions. */
export type PurchaseRequestDetailEditor = ReturnType<typeof usePurchaseRequestInlineEdit>
