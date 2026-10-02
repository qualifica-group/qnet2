import { z } from 'zod'
import type { TFunction } from 'i18next'
import { ADDITIONAL_DESCRIPTION_MAX_LENGTH, MAX_LINES_PER_TAB, QUANTITY_MAX, UNIT_PRICE_MAX } from '@/features/quotes/quote-schema'
import { round2 } from '@/features/quotes/quote-totals'
import type { SyncWorkOrderCostsPayload, WorkOrderCostLine } from '@/features/work-order-costs/types'

/** Backend `document_reference` ceiling (`string(100)`). */
export const DOCUMENT_REFERENCE_MAX_LENGTH = 100

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/

/** Display-only values of a row (never sent): they label pickers and feed the live amounts. */
const displaySchema = z.object({
  product_code: z.string().nullable(),
  product_name: z.string().nullable(),
  product_category: z.string().nullable(),
  unit_symbol: z.string().nullable(),
  vat_name: z.string().nullable(),
  vat_percent: z.number().nullable(),
  supplier_name: z.string().nullable(),
})

export type WorkOrderCostRowDisplay = z.infer<typeof displaySchema>

export const EMPTY_ROW_DISPLAY: WorkOrderCostRowDisplay = {
  product_code: null,
  product_name: null,
  product_category: null,
  unit_symbol: null,
  vat_name: null,
  vat_percent: null,
  supplier_name: null,
}

function hasMaxTwoDecimals(value: number): boolean {
  return round2(value) === value
}

/** Today as `YYYY-MM-DD` in the operator's local calendar (D-6). */
export function todayIsoDate(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

/**
 * One real cost row. Held nullable-per-field so controlled inputs can be
 * empty while typing; `superRefine` mirrors `QuoteLineRules::fieldRules()`
 * plus the work-order-specific `incurred_on` / text limits, each issue keyed
 * to its own field path.
 */
export function workOrderCostRowSchema(t: TFunction) {
  return z
    .object({
      id: z.number().optional(),
      product_id: z.number().nullable(),
      quantity: z.number().nullable(),
      unit_price: z.number().nullable(),
      vat_rate_id: z.number().nullable(),
      quote_line_id: z.number().nullable(),
      incurred_on: z.string(),
      supplier_id: z.number().nullable(),
      document_reference: z.string().nullable(),
      additional_description: z.string().nullable(),
      display: displaySchema,
    })
    .superRefine((row, ctx) => {
      const fail = (path: string, key: string) =>
        ctx.addIssue({ code: z.ZodIssueCode.custom, path: [path], message: t(`workOrders.costs.editor.errors.${key}`) })

      if (row.product_id === null) fail('product_id', 'productRequired')

      if (row.quantity === null) fail('quantity', 'quantityRequired')
      else if (row.quantity <= 0 || row.quantity > QUANTITY_MAX) fail('quantity', 'quantityInvalid')
      else if (!hasMaxTwoDecimals(row.quantity)) fail('quantity', 'decimals')

      if (row.unit_price === null) fail('unit_price', 'unitPriceRequired')
      else if (row.unit_price < 0 || row.unit_price > UNIT_PRICE_MAX) fail('unit_price', 'unitPriceInvalid')
      else if (!hasMaxTwoDecimals(row.unit_price)) fail('unit_price', 'decimals')

      if (!ISO_DATE.test(row.incurred_on) || Number.isNaN(Date.parse(row.incurred_on))) {
        fail('incurred_on', 'incurredOnInvalid')
      }

      if ((row.document_reference?.length ?? 0) > DOCUMENT_REFERENCE_MAX_LENGTH) {
        fail('document_reference', 'documentReferenceTooLong')
      }
      if ((row.additional_description?.length ?? 0) > ADDITIONAL_DESCRIPTION_MAX_LENGTH) {
        fail('additional_description', 'additionalDescriptionTooLong')
      }
    })
}

export function buildWorkOrderCostsSchema(t: TFunction) {
  return z.object({ lines: z.array(workOrderCostRowSchema(t)).max(MAX_LINES_PER_TAB) })
}

export type WorkOrderCostsFormValues = z.infer<ReturnType<typeof buildWorkOrderCostsSchema>>
export type WorkOrderCostRowValues = WorkOrderCostsFormValues['lines'][number]

/** A freshly added row: today's date (D-6), everything else empty. */
export function createEmptyCostRow(): WorkOrderCostRowValues {
  return {
    product_id: null,
    quantity: null,
    unit_price: null,
    vat_rate_id: null,
    quote_line_id: null,
    incurred_on: todayIsoDate(),
    supplier_id: null,
    document_reference: null,
    additional_description: null,
    display: EMPTY_ROW_DISPLAY,
  }
}

/** Hydrates the editor from the persisted rows (array order = `sort_order`). */
export function rowsFromCostLines(lines: WorkOrderCostLine[]): WorkOrderCostRowValues[] {
  return [...lines]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((line) => ({
      id: line.id,
      product_id: line.product_id,
      quantity: Number(line.quantity),
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate_id,
      quote_line_id: line.quote_line_id,
      incurred_on: line.incurred_on,
      supplier_id: line.supplier_id,
      document_reference: line.document_reference,
      additional_description: line.additional_description,
      display: {
        product_code: line.product.code,
        product_name: line.product.name,
        product_category: line.product.category?.name ?? null,
        unit_symbol: line.unit_of_measure?.symbol ?? null,
        vat_name: line.vat_rate?.name ?? null,
        vat_percent: line.vat_rate ? Number(line.vat_rate.rate) : null,
        supplier_name: line.supplier?.name ?? null,
      },
    }))
}

/** The exact PUT body of the contract: inputs only, blank text normalised to `null`. */
export function toCostsPayload(values: WorkOrderCostsFormValues): SyncWorkOrderCostsPayload {
  return {
    lines: values.lines.map((row) => ({
      ...(row.id !== undefined ? { id: row.id } : {}),
      product_id: row.product_id as number,
      quantity: row.quantity as number,
      unit_price: row.unit_price as number,
      vat_rate_id: row.vat_rate_id,
      quote_line_id: row.quote_line_id,
      incurred_on: row.incurred_on,
      supplier_id: row.supplier_id,
      document_reference: row.document_reference?.trim() ? row.document_reference.trim() : null,
      additional_description: row.additional_description?.trim() ? row.additional_description : null,
    })),
  }
}
