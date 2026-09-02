import { useEffect, useMemo, useRef, useState } from 'react'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { seedAttributeValues, toAttributeValuesMap } from '@/features/attributes/attribute-values'
import { createWorkOrder, updateWorkOrder } from '@/features/work-orders/api'
import { useWorkOrderFormContext } from '@/features/work-orders/use-work-order-form-context'
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
        // An empty PHP map serializes as a JSON ARRAY (`[]`, not `{}`):
        // `toAttributeValuesMap` normalizes that edge case before it reaches
        // RHF's `z.object(shape)` (mirrors `useQuoteForm`).
        attribute_values: toAttributeValuesMap(workOrder.attribute_values),
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
      attribute_values: {},
    }
  }, [mode, initialCode])

  // Indirezione stabile (mirrors `useQuoteForm`): `useForm` riceve un resolver
  // che non cambia mai identita', ma che esegue sempre l'ultimo schema
  // costruito dal set di attributi risolto live.
  const baseSchema = isEdit ? buildUpdateWorkOrderSchema(t) : buildCreateWorkOrderSchema(t)
  const resolverRef = useRef<Resolver<WorkOrderFormValues>>(zodResolver(baseSchema))

  const form = useForm<WorkOrderFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
  })

  // Spec 0098 D-1: le categorie vengono dalle righe COMMESSA scelte finora.
  const quoteLineIds = useWatch({ control: form.control, name: 'quote_line_ids' })
  const { context: attributeContext, isLoading: attributesLoading, hasPickedLines } =
    useWorkOrderFormContext(quoteLineIds ?? [])

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateWorkOrderSchema(t, attributeContext.applicable_attributes)
        : buildCreateWorkOrderSchema(t, attributeContext.applicable_attributes),
    [isEdit, t, attributeContext.applicable_attributes],
  )

  useEffect(() => {
    resolverRef.current = zodResolver(schema)
  }, [schema])

  // Il set applicabile arriva DOPO la costruzione del form: senza seminare una
  // chiave per ogni `code` risolto, l'oggetto Zod appena swappato rifiuterebbe
  // quelle mancanti e `handleSubmit` abortirebbe in silenzio. `setValue` sulla
  // mappa intera, non `reset`: gli altri campi gia' compilati restano.
  useEffect(() => {
    form.setValue(
      'attribute_values',
      seedAttributeValues(attributeContext.applicable_attributes, form.getValues('attribute_values')),
    )
  }, [attributeContext.applicable_attributes, form])

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
    attributeContext,
    attributesLoading,
    hasPickedLines,
  }
}
