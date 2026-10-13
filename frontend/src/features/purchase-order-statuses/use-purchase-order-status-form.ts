import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  PURCHASE_ORDER_STATUS_CATALOG_KEY,
  purchaseOrderStatusDetailQueryKey,
  updatePurchaseOrderStatus,
} from '@/features/purchase-order-statuses/api'
import { buildUpdatePayload } from '@/features/purchase-order-statuses/purchase-order-status-payload'
import {
  buildPurchaseOrderStatusSchema,
  type PurchaseOrderStatusFormValues,
} from '@/features/purchase-order-statuses/purchase-order-status-schema'
import type { PurchaseOrderStatusDetail } from '@/features/purchase-order-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'icon'] as const

interface UsePurchaseOrderStatusFormArgs {
  status: PurchaseOrderStatusDetail
  /** Called after a successful save so the caller can close the row + refresh. */
  onSuccess: () => void
}

/**
 * Owns every non-render concern of the ODA status in-place edit: RHF/Zod
 * wiring, 422 mapping and the diff PATCH. A save also refreshes the shared
 * catalog, so badges and tabs follow the new name/color/icon at once.
 */
export function usePurchaseOrderStatusForm({ status, onSuccess }: UsePurchaseOrderStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildPurchaseOrderStatusSchema(t), [t])

  const defaultValues = useMemo<PurchaseOrderStatusFormValues>(
    () => ({ name: status.name, color: status.color, icon: status.icon ?? '' }),
    [status],
  )

  // A saved record re-syncs the form; `keepDirtyValues` spares a row still open over a refetch.
  const form = useForm<PurchaseOrderStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: defaultValues,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: PurchaseOrderStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<PurchaseOrderStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      const saved = await updatePurchaseOrderStatus(status.id, buildUpdatePayload(values, status))
      queryClient.setQueryData(purchaseOrderStatusDetailQueryKey(status.id), saved)
      void queryClient.invalidateQueries({ queryKey: PURCHASE_ORDER_STATUS_CATALOG_KEY })
      toast.success(t('purchaseOrderStatuses.form.updated'))
      onSuccess()
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('purchaseOrderStatuses.form.genericError'))
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
