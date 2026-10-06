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
import { createWorkOrder, updateWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import {
  useWorkOrderFormContext,
  type PersistedWorkOrderContext,
} from '@/features/work-orders/use-work-order-form-context'
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

/** Empty slots rendered on a fresh create, mirroring opportunities/quotes' own default. */
const DEFAULT_PARTICIPANT_SLOTS = 4

/** Server-side field names mapped onto the form for 422 handling. */
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
  'quote_line_ids',
  'task_template_id',
] as const

export type WorkOrderFormValues = CreateWorkOrderFormValues & UpdateWorkOrderFormValues

/** Default values hydrated from the persisted work order (the in-place detail). */
function editDefaults(workOrder: WorkOrderDetail): WorkOrderFormValues {
  return {
    code: workOrder.code,
    quote_id: workOrder.quote?.id ?? null,
    title: workOrder.title,
    type: workOrder.type,
    start_date: workOrder.start_date,
    supervisor_ids: workOrder.supervisors.map((supervisor) => supervisor.id),
    // Gaps in `position` are meaningful: rebuild the sparse array rather
    // than compacting the persisted participants into a dense list.
    participant_slots: padManagerSlots(managerSlotsFromRefs(workOrder.participants), DEFAULT_PARTICIPANT_SLOTS),
    callback_date: workOrder.callback_date,
    description: workOrder.description,
    internal_notes: workOrder.internal_notes,
    quote_line_ids: workOrder.quote_lines.map((line) => line.id),
    // Spec 0124 D-9: never resubmitted (`buildUpdatePayload` never reads it,
    // D-5), kept only so the shared shape stays one.
    task_template_id: workOrder.task_template?.id ?? null,
    // An empty PHP map serializes as a JSON ARRAY (`toAttributeValuesMap`),
    // and every applicable code needs its key for the Zod object: seeded
    // here, not by the effect below, so the `values` re-sync after a save
    // never hands the schema an unseeded map.
    attribute_values: seedAttributeValues(
      workOrder.applicable_attributes,
      toAttributeValuesMap(workOrder.attribute_values),
    ),
  }
}

/** Default values of a brand-new work order, its `code` prefilled with the suggestion (D-1). */
function createDefaults(initialCode: string | undefined): WorkOrderFormValues {
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
    quote_line_ids: [],
    task_template_id: null,
    attribute_values: {},
  }
}

interface UseWorkOrderFormArgs {
  mode: WorkOrderFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrder: WorkOrderDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-1). */
  initialCode?: string
}

/**
 * Owns every non-render concern of the work order create form
 * (`WorkOrderFormBody`) and of the in-place detail (`useWorkOrderInlineEdit`,
 * edit mode): RHF/Zod wiring,
 * default values, the AC-072 offer/lines coherence, server 422 mapping and the create/update submit.
 */
export function useWorkOrderForm({ mode, onSuccess, initialCode }: UseWorkOrderFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const defaultValues = useMemo<WorkOrderFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.workOrder) : createDefaults(initialCode)),
    [mode, initialCode],
  )

  // Indirezione stabile (mirrors `useQuoteForm`): `useForm` riceve un resolver
  // che non cambia mai identita', ma che esegue sempre l'ultimo schema
  // costruito dal set di attributi risolto live.
  const baseSchema = isEdit ? buildUpdateWorkOrderSchema(t) : buildCreateWorkOrderSchema(t)
  const resolverRef = useRef<Resolver<WorkOrderFormValues>>(zodResolver(baseSchema))

  // Edit mode IS the work order detail (spec 0195 applied to Commesse): the
  // persisted record can change under the form, so `values` re-syncs it while
  // `keepDirtyValues` preserves the row still being edited. Every explicit
  // `reset` that means to DROP an edit passes `keepDirtyValues: false` (RHF
  // merges `resetOptions` into every reset, explicit options winning).
  const form = useForm<WorkOrderFormValues>({
    resolver: (values, context, options) => resolverRef.current(values, context, options),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  const persistedContext = useMemo<PersistedWorkOrderContext | undefined>(
    () =>
      mode.type === 'edit'
        ? {
            quoteLineIds: mode.workOrder.quote_lines.map((line) => line.id),
            context: {
              applicable_attributes: mode.workOrder.applicable_attributes,
              attribute_layout: mode.workOrder.attribute_layout,
            },
          }
        : undefined,
    [mode],
  )

  // Spec 0098 D-1: le categorie vengono dalle righe COMMESSA scelte finora.
  const quoteLineIds = useWatch({ control: form.control, name: 'quote_line_ids' })
  const { context: attributeContext, isLoading: attributesLoading, hasPickedLines } =
    useWorkOrderFormContext(quoteLineIds ?? [], persistedContext)

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

  const onSubmit = async (values: WorkOrderFormValues) => {
    setServerError(null)
    const errorFields: Path<WorkOrderFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        // Step 1 (edit): PATCH what changed and refresh the cached detail.
        const saved = await updateWorkOrder(mode.workOrder.id, buildUpdatePayload(values, mode.workOrder))
        queryClient.setQueryData(workOrderDetailQueryKey(mode.workOrder.id), saved)
        // Step 2 (edit): the detail stays mounted on the saved record, clean.
        form.reset(editDefaults(saved), { keepDirtyValues: false })
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
    clearServerError: () => setServerError(null),
    onSubmit,
    handleQuoteChange,
    attributeContext,
    attributesLoading,
    hasPickedLines,
  }
}

/** Everything the form's sections read: the form, its cascade handlers and the resolved Attributes. */
export type WorkOrderFormState = ReturnType<typeof useWorkOrderForm>
