import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createPurchaseRequestPriority, purchaseRequestPriorityDetailQueryKey, updatePurchaseRequestPriority } from '@/features/purchase-request-priorities/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/purchase-request-priorities/purchase-request-priority-form-payload'
import {
  buildCreatePurchaseRequestPrioritySchema,
  buildUpdatePurchaseRequestPrioritySchema,
  type CreatePurchaseRequestPriorityFormValues,
  type UpdatePurchaseRequestPriorityFormValues,
} from '@/features/purchase-request-priorities/purchase-request-priority-schema'
import type { PurchaseRequestPriorityDetail, PurchaseRequestPriorityFormMode } from '@/features/purchase-request-priorities/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'description',
  'color',
  'icon',
  'is_active',
  'is_default',
] as const

export type PurchaseRequestPriorityFormValues = CreatePurchaseRequestPriorityFormValues & UpdatePurchaseRequestPriorityFormValues

interface UsePurchaseRequestPriorityFormArgs {
  mode: PurchaseRequestPriorityFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (purchaseRequestPriority: PurchaseRequestPriorityDetail) => void
}

/**
 * Owns every non-render concern of the RDA priority form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `PurchaseRequestPriorityForm`; edit backs the detail's in-place rows (diff PATCH). The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function usePurchaseRequestPriorityForm({ mode, onSuccess }: UsePurchaseRequestPriorityFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdatePurchaseRequestPrioritySchema(t) : buildCreatePurchaseRequestPrioritySchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<PurchaseRequestPriorityFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.purchaseRequestPriority.name,
        description: mode.purchaseRequestPriority.description,
        color: mode.purchaseRequestPriority.color,
        icon: mode.purchaseRequestPriority.icon ?? '',
        is_active: mode.purchaseRequestPriority.is_active,
        is_default: mode.purchaseRequestPriority.is_default,
      }
    }
    return {
      name: '',
      description: null,
      color: '',
      icon: '',
      is_active: true,
      is_default: false,
    }
  }, [mode])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<PurchaseRequestPriorityFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: PurchaseRequestPriorityFormValues) => {
    setServerError(null)
    const errorFields: Path<PurchaseRequestPriorityFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updatePurchaseRequestPriority(
          mode.purchaseRequestPriority.id,
          buildUpdatePayload(values, mode.purchaseRequestPriority),
        )
        queryClient.setQueryData(purchaseRequestPriorityDetailQueryKey(mode.purchaseRequestPriority.id), saved)
        toast.success(t('purchaseRequestPriorities.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createPurchaseRequestPriority(buildCreatePayload(values))
      toast.success(t('purchaseRequestPriorities.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('purchaseRequestPriorities.form.genericError'))
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
