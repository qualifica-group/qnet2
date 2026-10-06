import { useMemo } from 'react'
import { useFieldArray, useForm, useWatch, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { costRowPatchFromProduct } from '@/features/work-order-costs/cost-row-product'
import type { CostRowErrors } from '@/features/work-order-costs/cost-row-error'
import { useSyncWorkOrderCosts } from '@/features/work-order-costs/use-work-order-costs'
import {
  buildWorkOrderCostsSchema,
  createEmptyCostRow,
  rowsFromCostLines,
  toCostsPayload,
  type WorkOrderCostRowValues,
  type WorkOrderCostsFormValues,
} from '@/features/work-order-costs/work-order-costs-schema'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { WorkOrderCostLine } from '@/features/work-order-costs/types'

/** Row fields a 422 may be mapped onto (`lines.<n>.<field>`). */
const ROW_ERROR_FIELDS = [
  'product_id',
  'quantity',
  'unit_price',
  'vat_rate_id',
  'quote_line_id',
  'incurred_on',
  'supplier_id',
  'document_reference',
  'additional_description',
] as const

/**
 * Owns the costs grid: the RHF form (rows hydrated from the persisted lines,
 * reset whenever they change), row editing and the single save that replaces
 * the whole set. Server 422s are mapped back onto the offending row field.
 */
export function useWorkOrderCostsEditor(workOrderId: number, lines: WorkOrderCostLine[]) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildWorkOrderCostsSchema(t), [t])
  const values = useMemo<WorkOrderCostsFormValues>(() => ({ lines: rowsFromCostLines(lines) }), [lines])
  const form = useForm<WorkOrderCostsFormValues>({ resolver: zodResolver(schema), values })
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'lines' })
  const rows = useWatch({ control: form.control, name: 'lines' })
  const sync = useSyncWorkOrderCosts(workOrderId)
  const { isSubmitted } = form.formState

  const changeField = (index: number, patch: Partial<WorkOrderCostRowValues>) =>
    form.setValue(
      `lines.${index}`,
      { ...form.getValues(`lines.${index}`), ...patch },
      { shouldDirty: true, shouldValidate: isSubmitted },
    )

  const changeProduct = (index: number, item: QuoteProductForSelectItem | null) =>
    item
      ? changeField(index, costRowPatchFromProduct(item, form.getValues(`lines.${index}`)))
      : changeField(index, { product_id: null })

  const submit = form.handleSubmit(async (formValues) => {
    try {
      await sync.mutateAsync(toCostsPayload(formValues))
      toast.success(t('workOrders.costs.editor.saved'))
    } catch (error) {
      const paths = formValues.lines.flatMap((_, index) =>
        ROW_ERROR_FIELDS.map((field) => `lines.${index}.${field}` as Path<WorkOrderCostsFormValues>),
      )
      if (!applyServerValidationErrors(error, form.setError, paths)) {
        toast.error(t('workOrders.costs.editor.saveError'))
      }
    }
  })

  const rowErrors = (index: number): CostRowErrors | undefined => form.formState.errors.lines?.[index]

  return {
    fields,
    rows,
    rowErrors,
    addRow: () => append(createEmptyCostRow()),
    removeRow: remove,
    changeField,
    changeProduct,
    submit,
    cancel: () => form.reset(),
    isSaving: sync.isPending,
    isDirty: form.formState.isDirty,
  }
}
