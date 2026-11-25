import { useEffect, useMemo, useRef } from 'react'
import { useFormSubmitError } from '@/features/auth/use-form-submit-error'
import { useForm, useWatch } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { seedAttributeValues } from '@/features/attributes/attribute-values'
import {
  createStandaloneProjectWorkOrder,
  createWorkOrder,
  updateWorkOrder,
  workOrderDetailQueryKey,
} from '@/features/work-orders/api'
import {
  useWorkOrderFormContext,
  type PersistedWorkOrderContext,
} from '@/features/work-orders/use-work-order-form-context'
import {
  buildCreatePayload,
  buildStandaloneCreatePayload,
  buildUpdatePayload,
} from '@/features/work-orders/work-order-form-payload'
import {
  buildCreateStandaloneWorkOrderSchema,
  buildCreateWorkOrderSchema,
  buildUpdateWorkOrderSchema,
  type CreateWorkOrderFormValues,
  type UpdateWorkOrderFormValues,
} from '@/features/work-orders/work-order-schema'
import type { TFunction } from 'i18next'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type {
  ApplicableAttributeSummary,
  WorkOrderDetail,
  WorkOrderFormMode,
} from '@/features/work-orders/types'
import { createDefaults, editDefaults } from '@/features/work-orders/work-order-form-defaults'

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
  'work_order_workflow_status_id',
  'registry_id',
  'company_id',
  'company_site_id',
  'operational_site_id',
  'product_lines',
] as const

export type WorkOrderFormValues = CreateWorkOrderFormValues & UpdateWorkOrderFormValues

/** The schema of the mode: create from an offer, create without one (spec 0431), or the detail's edit. */
function buildSchema(
  t: TFunction,
  isEdit: boolean,
  isStandalone: boolean,
  attributes?: ApplicableAttributeSummary[],
  layout?: LayoutBlob | null,
) {
  if (isEdit) {
    return buildUpdateWorkOrderSchema(t, attributes, layout, isStandalone)
  }

  return isStandalone
    ? buildCreateStandaloneWorkOrderSchema(t, attributes, layout)
    : buildCreateWorkOrderSchema(t, attributes, layout)
}

interface UseWorkOrderFormArgs {
  mode: WorkOrderFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (workOrder: WorkOrderDetail) => void
  /** Create-only: sequential code suggestion prefilled into the `code` default (D-1). */
  initialCode?: string
  /** Create-only (spec 0431): a project work order born without an offer. */
  standalone?: boolean
}

/**
 * Owns every non-render concern of the work order create form
 * (`WorkOrderFormBody`) and of the in-place detail (`useWorkOrderInlineEdit`,
 * edit mode): RHF/Zod wiring,
 * default values, the AC-072 offer/lines coherence, server 422 mapping and the create/update submit.
 */
export function useWorkOrderForm({ mode, onSuccess, initialCode, standalone = false }: UseWorkOrderFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useFormSubmitError()

  const isEdit = mode.type === 'edit'
  // Spec 0431: born without an offer (create), or such a persisted one (edit).
  const isStandalone = mode.type === 'edit' ? mode.workOrder.is_standalone === true : standalone

  const defaultValues = useMemo<WorkOrderFormValues>(
    () => (mode.type === 'edit' ? editDefaults(mode.workOrder) : createDefaults(initialCode, standalone)),
    [mode, initialCode, standalone],
  )

  // Indirezione stabile (mirrors `useQuoteForm`): `useForm` riceve un resolver
  // che non cambia mai identita', ma che esegue sempre l'ultimo schema
  // costruito dal set di attributi risolto live.
  const baseSchema = buildSchema(t, isEdit, isStandalone)
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
              locked_attributes: mode.workOrder.locked_attributes,
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
      buildSchema(t, isEdit, isStandalone, attributeContext.applicable_attributes, attributeContext.attribute_layout),
    [isEdit, isStandalone, t, attributeContext.applicable_attributes, attributeContext.attribute_layout],
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

      const created = isStandalone
        ? await createStandaloneProjectWorkOrder(buildStandaloneCreatePayload(values, initialCode))
        : await createWorkOrder(buildCreatePayload(values, initialCode))
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
    isStandalone,
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
