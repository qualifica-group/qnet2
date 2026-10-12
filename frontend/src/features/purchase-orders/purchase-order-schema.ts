import { z } from 'zod'
import type { TFunction } from 'i18next'
import { MAX_COPIES, MIN_COPIES } from '@/features/purchase-orders/types'

/**
 * Zod schema of the ODA form, mirroring the frozen server rules of spec 0210
 * (the client never replaces server validation). Factory takes `t` so the
 * messages are localized (`purchaseOrderForm.errors.*`).
 */

export const LINES_MIN = 1
export const LINES_MAX = 200
const LINE_DESCRIPTION_MAX = 500
const GENERAL_NOTES_MAX = 5000
const SECTOR_NOTES_MAX = 500
const QUANTITY_MAX = 999_999_999
const UNIT_PRICE_LIMIT = 99_999_999_999

/** Required relation id: the form holds `null` until the user picks one. */
function requiredId(message: string) {
  return z
    .number()
    .nullable()
    .refine((value): boolean => value !== null, message)
}

function buildLineSchema(t: TFunction) {
  return z.object({
    /** Present on a persisted line, absent on a new one. */
    id: z.number().int().optional(),
    /** Read-only context of a line generated from an RDA line (D-18); never sent. */
    purchase_request_id: z.number().int().nullable(),
    vat_rate_percent: z.number().nullable(),
    product_id: z.number().int().nullable(),
    description: z
      .string()
      .trim()
      .min(1, t('purchaseOrderForm.errors.lineDescriptionRequired'))
      .max(LINE_DESCRIPTION_MAX, t('purchaseOrderForm.errors.lineDescriptionMax')),
    cost_type_id: requiredId(t('purchaseOrderForm.errors.costTypeRequired')),
    unit_of_measure_id: z.number().int().nullable(),
    quantity: z
      .number(t('purchaseOrderForm.errors.quantityInvalid'))
      .gt(0, t('purchaseOrderForm.errors.quantityPositive'))
      .max(QUANTITY_MAX, t('purchaseOrderForm.errors.quantityMax')),
    unit_price: z
      .number(t('purchaseOrderForm.errors.unitPriceInvalid'))
      .min(0, t('purchaseOrderForm.errors.unitPriceNegative'))
      .max(UNIT_PRICE_LIMIT, t('purchaseOrderForm.errors.unitPriceMax')),
    vat_rate_id: z.number().int().nullable(),
    work_order_id: z.number().int().nullable(),
  })
}

export function buildPurchaseOrderSchema(t: TFunction) {
  return z.object({
    supplier_id: requiredId(t('purchaseOrderForm.errors.supplierRequired')),
    /** UI-only: false while the picked supplier is not qualified (D-15); never sent. */
    supplier_qualified: z.boolean(),
    company_id: requiredId(t('purchaseOrderForm.errors.companyRequired')),
    company_site_id: requiredId(t('purchaseOrderForm.errors.companySiteRequired')),
    operational_site_id: z.number().int().nullable(),
    order_date: z.string().min(1, t('purchaseOrderForm.errors.orderDateRequired')),
    description: z.string().trim().min(1, t('purchaseOrderForm.errors.descriptionRequired')),
    general_notes: z.string().max(GENERAL_NOTES_MAX, t('purchaseOrderForm.errors.textMax')),
    payment_method_id: z.number().int().nullable(),
    is_internal_user: z.boolean(),
    customer_id: z.number().int().nullable(),
    work_order_id: z.number().int().nullable(),
    quote_id: z.number().int().nullable(),
    opportunity_id: z.number().int().nullable(),
    sector_ids: z.array(z.number().int()),
    sector_notes: z.string().max(SECTOR_NOTES_MAX, t('purchaseOrderForm.errors.textMax')),
    /** Creation only (D-16); ignored by the payload on update. */
    copies: z
      .number(t('purchaseOrderForm.errors.copiesRange'))
      .int(t('purchaseOrderForm.errors.copiesRange'))
      .min(MIN_COPIES, t('purchaseOrderForm.errors.copiesRange'))
      .max(MAX_COPIES, t('purchaseOrderForm.errors.copiesRange')),
    pending_files: z.array(z.custom<File>()),
    lines: z
      .array(buildLineSchema(t))
      .min(LINES_MIN, t('purchaseOrderForm.errors.linesRequired'))
      .max(LINES_MAX, t('purchaseOrderForm.errors.linesMax')),
  })
}

export type PurchaseOrderFormValues = z.infer<ReturnType<typeof buildPurchaseOrderSchema>>
export type PurchaseOrderLineFormValues = PurchaseOrderFormValues['lines'][number]
