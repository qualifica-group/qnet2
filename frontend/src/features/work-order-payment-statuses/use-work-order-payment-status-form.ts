import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createWorkOrderPaymentStatus, workOrderPaymentStatusDetailQueryKey, updateWorkOrderPaymentStatus } from '@/features/work-order-payment-statuses/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/work-order-payment-statuses/work-order-payment-status-form-payload'
import {
  buildCreateWorkOrderPaymentStatusSchema,
  buildUpdateWorkOrderPaymentStatusSchema,
  type CreateWorkOrderPaymentStatusFormValues,
  type UpdateWorkOrderPaymentStatusFormValues,
} from '@/features/work-order-payment-statuses/work-order-payment-status-schema'
import type {
  WorkOrderPaymentStatusDetail,
  WorkOrderPaymentStatusFormMode,
} from '@/features/work-order-payment-statuses/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'color', 'is_active', 'allows_delivery'] as const

export type WorkOrderPaymentStatusFormValues = CreateWorkOrderPaymentStatusFormValues & UpdateWorkOrderPaymentStatusFormValues

interface UseWorkOrderPaymentStatusFormArgs {
  mode: WorkOrderPaymentStatusFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrderPaymentStatus: WorkOrderPaymentStatusDetail) => void
}

/**
 * Owns every non-render concern of `WorkOrderPaymentStatusForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `WorkOrderPaymentStatusForm`; edit backs the detail's in-place rows (diff PATCH).
 * The component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useWorkOrderPaymentStatusForm({ mode, onSuccess }: UseWorkOrderPaymentStatusFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateWorkOrderPaymentStatusSchema(t) : buildCreateWorkOrderPaymentStatusSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<WorkOrderPaymentStatusFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.workOrderPaymentStatus.name,
        description: mode.workOrderPaymentStatus.description,
        color: mode.workOrderPaymentStatus.color,
        is_active: mode.workOrderPaymentStatus.is_active,
        allows_delivery: mode.workOrderPaymentStatus.allows_delivery,
      }
    }
    return { name: '', description: null, color: '', is_active: true, allows_delivery: false }
  }, [mode])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<WorkOrderPaymentStatusFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: WorkOrderPaymentStatusFormValues) => {
    setServerError(null)
    const errorFields: Path<WorkOrderPaymentStatusFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateWorkOrderPaymentStatus(
          mode.workOrderPaymentStatus.id,
          buildUpdatePayload(values, mode.workOrderPaymentStatus),
        )
        queryClient.setQueryData(workOrderPaymentStatusDetailQueryKey(mode.workOrderPaymentStatus.id), saved)
        toast.success(t('workOrderPaymentStatuses.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createWorkOrderPaymentStatus(buildCreatePayload(values))
      toast.success(t('workOrderPaymentStatuses.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('workOrderPaymentStatuses.form.genericError'))
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
