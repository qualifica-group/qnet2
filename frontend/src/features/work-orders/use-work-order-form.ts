import { useMemo, useState } from 'react'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createWorkOrder, updateWorkOrder } from '@/features/work-orders/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/work-orders/work-order-form-payload'
import {
  buildCreateWorkOrderSchema,
  buildUpdateWorkOrderSchema,
  type CreateWorkOrderFormValues,
  type UpdateWorkOrderFormValues,
} from '@/features/work-orders/work-order-schema'
import type { WorkOrderDetail, WorkOrderFormMode } from '@/features/work-orders/types'

/** Server-side field names mapped onto the form for 422 handling. */
/** Empty slots rendered on a fresh create, mirroring opportunities/quotes' own default. */
const DEFAULT_PARTICIPANT_SLOTS = 4

const SERVER_ERROR_FIELDS = [
  'code',
  'quote_id',
  'title',
  'type',
  'start_date',
  'supervisor_ids',
  'participant_slots',
  'callback_date',
  'description',
  'internal_notes',
  'is_force_closed',
  'force_close_reason',
  'quote_line_ids',
] as const

export type WorkOrderFormValues = CreateWorkOrderFormValues & UpdateWorkOrderFormValues

interface UseWorkOrderFormArgs {
  mode: WorkOrderFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrder: WorkOrderDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-1). */
  initialCode?: string
}

/**
 * Owns every non-render concern of `WorkOrderFormBody`: RHF/Zod wiring,
 * default values, the AC-072 offer/lines coherence, the D-4 force-close
 * reason reset, server 422 mapping and the create/update submit.
 */
export function useWorkOrderForm({ mode, onSuccess, initialCode }: UseWorkOrderFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateWorkOrderSchema(t) : buildCreateWorkOrderSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<WorkOrderFormValues>(() => {
    if (mode.type === 'edit') {
      const { workOrder } = mode
      return {
        code: workOrder.code,
        quote_id: workOrder.quote?.id ?? null,
        title: workOrder.title,
        type: workOrder.type,
        start_date: workOrder.start_date,
        supervisor_ids: workOrder.supervisors.map((supervisor) => supervisor.id),
        // Gaps in `position` are meaningful: rebuild the sparse array rather
        // than compacting the persisted participants into a dense list.
        participant_slots: padManagerSlots(
          managerSlotsFromRefs(workOrder.participants),
          DEFAULT_PARTICIPANT_SLOTS,
        ),
        callback_date: workOrder.callback_date,
        description: workOrder.description,
        internal_notes: workOrder.internal_notes,
        is_force_closed: workOrder.is_force_closed,
        force_close_reason: workOrder.force_close_reason,
        quote_line_ids: workOrder.quote_lines.map((line) => line.id),
      }
    }
    return {
      code: initialCode ?? '',
      quote_id: null,
      title: '',
      type: 'processing',
      start_date: '',
      supervisor_ids: [],
      participant_slots: Array.from({ length: DEFAULT_PARTICIPANT_SLOTS }, () => null),
      callback_date: null,
      description: null,
      internal_notes: null,
      is_force_closed: false,
      force_close_reason: null,
      quote_line_ids: [],
    }
  }, [mode, initialCode])

  const form = useForm<WorkOrderFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // AC-072: every quote line belongs to exactly one offer, so once the offer
  // changes, none of the previously selected lines can still be valid — the
  // whole selection is dropped rather than pruned line by line.
  const handleQuoteChange = () => {
    form.setValue('quote_line_ids', [])
  }

  // D-4: the reason is meaningless once the closure is no longer forced, so
  // it is cleared the moment the toggle turns off, not left for the payload
  // builder to silently drop.
  const handleForceClosedChange = (checked: boolean) => {
    form.setValue('is_force_closed', checked)
    if (!checked) {
      form.setValue('force_close_reason', null)
    }
  }

  const onSubmit = async (values: WorkOrderFormValues) => {
    setServerError(null)
    const errorFields: Path<WorkOrderFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateWorkOrder(
          mode.workOrder.id,
          buildUpdatePayload(values, mode.workOrder),
        )
        queryClient.setQueryData(['work-orders', 'detail', mode.workOrder.id], saved)
        toast.success(t('workOrders.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createWorkOrder(buildCreatePayload(values))
      toast.success(t('workOrders.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('workOrders.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
    handleQuoteChange,
    handleForceClosedChange,
  }
}
