import { z } from 'zod'
import type { TFunction } from 'i18next'
import { PURCHASE_INVOICE_TYPES } from '@/features/purchase-invoices/types'

/**
 * Zod schemas of the passive invoice forms, mirroring the frozen server rules
 * of spec 0250 (the client never replaces server validation). Factories take
 * `t` so messages are localized (`purchaseInvoices.editor.errors.*`).
 */

export const PURCHASE_INVOICE_LINES_MIN = 1
export const PURCHASE_INVOICE_LINES_MAX = 200
const DESCRIPTION_MAX = 500
const NOTES_MAX = 5000
const DOCUMENT_NUMBER_MAX = 30
const QUANTITY_MAX = 9_999_999
const UNIT_PRICE_LIMIT = 9_999_999_999

const nullableId = z.number().int().nullable()

/** One document row; amounts are numbers, the server recomputes net/VAT/total. */
export function buildPurchaseInvoiceLineSchema(t: TFunction) {
  const e = (key: string) => t(`purchaseInvoices.editor.errors.${key}`)
  return z
    .object({
      id: nullableId,
      purchase_order_line_id: nullableId,
      product_id: nullableId,
      description: z.string().trim().min(1, e('descriptionRequired')).max(DESCRIPTION_MAX, e('descriptionMax')),
      quantity: z.number(e('quantityInvalid')).gt(0, e('quantityPositive')).max(QUANTITY_MAX, e('quantityMax')),
      unit_price: z
        .number(e('unitPriceInvalid'))
        .min(-UNIT_PRICE_LIMIT, e('unitPriceRange'))
        .max(UNIT_PRICE_LIMIT, e('unitPriceRange')),
      vat_rate_id: nullableId,
      cost_type_id: z.number(e('costTypeRequired')).int(),
      work_order_id: nullableId,
      /** Billable remainder of an ODA row taken from the draft (spec 0254); UI-only, never sent. */
      available_quantity: z.number().nullable(),
    })
    .superRefine((line, ctx) => {
      const max = line.available_quantity
      if (max !== null && toThousandths(line.quantity) > toThousandths(max)) {
        ctx.addIssue({
          code: 'custom',
          path: ['quantity'],
          message: t('purchaseInvoices.editor.errors.quantityAboveAvailable', { max }),
        })
      }
    })
}

/** Compares quantities on their 3 decimals, free of float noise. */
function toThousandths(value: number): number {
  return Math.round(value * 1000)
}

/** Write form (create + edit): header, defaults, notes and 1..200 rows. */
export function buildPurchaseInvoiceWriteSchema(t: TFunction) {
  const e = (key: string) => t(`purchaseInvoices.editor.errors.${key}`)
  return z
    .object({
      document_type: z.enum(PURCHASE_INVOICE_TYPES),
      document_number: z.string().max(DOCUMENT_NUMBER_MAX, e('documentNumberMax')).nullable(),
      document_date: z.string().min(1, e('documentDateRequired')),
      company_id: z.number(e('companyRequired')).int(),
      supplier_id: z.number(e('supplierRequired')).int(),
      payment_method_id: z.number(e('paymentMethodRequired')).int(),
      financial_account_id: nullableId,
      company_site_id: nullableId,
      operational_site_id: nullableId,
      cost_type_id: nullableId,
      work_order_id: nullableId,
      notes: z.string().max(NOTES_MAX, e('notesMax')).nullable(),
      purchase_order_ids: z.array(z.number().int()),
      lines: z
        .array(buildPurchaseInvoiceLineSchema(t))
        .min(PURCHASE_INVOICE_LINES_MIN, e('linesRequired'))
        .max(PURCHASE_INVOICE_LINES_MAX, e('linesMax')),
    })
    .superRefine((values, ctx) => {
      if (values.document_type === 'invoice' && !values.document_number?.trim()) {
        ctx.addIssue({ code: 'custom', path: ['document_number'], message: e('documentNumberRequired') })
      }
    })
}

export type PurchaseInvoiceWriteFormValues = z.infer<ReturnType<typeof buildPurchaseInvoiceWriteSchema>>
export type PurchaseInvoiceLineFormValues = PurchaseInvoiceWriteFormValues['lines'][number]

/** Details dialog: the supplier document number (empty means proforma) and the notes. */
export function buildPurchaseInvoiceDetailsSchema(t: TFunction) {
  return z.object({
    document_number: z
      .string()
      .max(DOCUMENT_NUMBER_MAX, t('purchaseInvoices.editor.errors.documentNumberMax'))
      .nullable(),
    notes: z.string().max(NOTES_MAX, t('purchaseInvoices.editor.errors.notesMax')).nullable(),
  })
}

export type PurchaseInvoiceDetailsFormValues = z.infer<ReturnType<typeof buildPurchaseInvoiceDetailsSchema>>
