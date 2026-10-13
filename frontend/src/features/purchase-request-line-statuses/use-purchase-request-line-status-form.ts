import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  purchaseRequestLineStatusDetailQueryKey,
  updatePurchaseRequestLineStatus,
} from '@/features/purchase-request-line-statuses/api'
import { buildUpdatePayload } from '@/features/purchase-request-line-statuses/purchase-request-line-status-form-payload'
import {
  buildPurchaseRequestLineStatusSchema,
  type PurchaseRequestLineStatusFormValues,
} from '@/features/purchase-request-line-statuses/purchase-request-line-status-schema'
import { PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import type {
  PurchaseRequestLineStatusDetail,
  PurchaseRequestLineStatusDetailWithPermissions,
} from '@/features/purchase-request-line-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'icon'] as const

interface UsePurchaseRequestLineStatusFormArgs {
  purchaseRequestLineStatus: PurchaseRequestLineStatusDetailWithPermissions
  /** Called after a successful save so the caller can close the row and refresh. */
  onSuccess: (saved: PurchaseRequestLineStatusDetail) => void
}

/**
 * Owns every non-render concern of the line status detail's in-place rows:
 * RHF/Zod wiring, server 422 mapping and the diff PATCH. A saved record also
 * invalidates the shared catalog, so badges, tabs and dialogs follow the rename.
 */
export function usePurchaseRequestLineStatusForm({ purchaseRequestLineStatus, onSuccess }: UsePurchaseRequestLineStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const schema = useMemo(() => buildPurchaseRequestLineStatusSchema(t), [t])

  const defaultValues = useMemo<PurchaseRequestLineStatusFormValues>(
    () => ({
      name: purchaseRequestLineStatus.name,
      color: purchaseRequestLineStatus.color,
      icon: purchaseRequestLineStatus.icon ?? '',
    }),
    [purchaseRequestLineStatus],
  )

  // A saved record re-syncs the form; `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<PurchaseRequestLineStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: defaultValues,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: PurchaseRequestLineStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<PurchaseRequestLineStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      // Step 1: PATCH only what changed
      const saved = await updatePurchaseRequestLineStatus(
        purchaseRequestLineStatus.id,
        buildUpdatePayload(values, purchaseRequestLineStatus),
      )
      // Step 2: re-sync the detail and the shared catalog
      queryClient.setQueryData(purchaseRequestLineStatusDetailQueryKey(purchaseRequestLineStatus.id), saved)
      void queryClient.invalidateQueries({ queryKey: PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY })
      toast.success(t('purchaseRequestLineStatuses.form.updated'))
      onSuccess(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('purchaseRequestLineStatuses.form.genericError'))
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
