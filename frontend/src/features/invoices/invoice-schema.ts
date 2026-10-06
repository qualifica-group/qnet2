import { z } from 'zod'
import type { TFunction } from 'i18next'
import { INVOICE_TAGS } from '@/features/invoices/types'

/**
 * Zod schemas of the invoice forms, mirroring the frozen server rules of
 * spec 0194 (the client never replaces server validation). Factories take
 * `t` so messages are localized (`invoiceEditor.errors.*`).
 */

export const INVOICE_LINES_MIN = 1
export const INVOICE_LINES_MAX = 200
const DESCRIPTION_MAX = 500
const NOTES_MAX = 5000
const EXTERNAL_NUMBER_MAX = 30
const QUANTITY_MAX = 9_999_999
const UNIT_PRICE_LIMIT = 9_999_999_999

const tagSchema = z.enum(INVOICE_TAGS).nullable()

/** One document line; amounts are numbers, the server recomputes net/VAT/total. */
export function buildInvoiceLineSchema(t: TFunction) {
  return z.object({
    quote_line_id: z.number().int().nullable(),
    product_id: z.number().int().nullable(),
    description: z
      .string()
      .trim()
      .min(1, t('invoiceEditor.errors.descriptionRequired'))
      .max(DESCRIPTION_MAX, t('invoiceEditor.errors.descriptionMax')),
    quantity: z
      .number(t('invoiceEditor.errors.quantityInvalid'))
      .gt(0, t('invoiceEditor.errors.quantityPositive'))
      .max(QUANTITY_MAX, t('invoiceEditor.errors.quantityMax')),
    unit_price: z
      .number(t('invoiceEditor.errors.unitPriceInvalid'))
      .min(-UNIT_PRICE_LIMIT, t('invoiceEditor.errors.unitPriceRange'))
      .max(UNIT_PRICE_LIMIT, t('invoiceEditor.errors.unitPriceRange')),
    vat_rate_id: z.number(t('invoiceEditor.errors.vatRateRequired')).int(),
  })
}

/** Write form (issue + edit): header, notes and 1..200 lines. */
export function buildInvoiceWriteSchema(t: TFunction) {
  return z.object({
    document_date: z.string().min(1, t('invoiceEditor.errors.documentDateRequired')),
    company_id: z.number(t('invoiceEditor.errors.companyRequired')).int(),
    customer_registry_id: z.number(t('invoiceEditor.errors.customerRequired')).int(),
    payment_method_id: z.number(t('invoiceEditor.errors.paymentMethodRequired')).int(),
    financial_account_id: z.number().int().nullable(),
    notes: z.string().max(NOTES_MAX, t('invoiceEditor.errors.notesMax')).nullable(),
    internal_note: z.string().max(NOTES_MAX, t('invoiceEditor.errors.notesMax')).nullable(),
    tag: tagSchema,
    lines: z
      .array(buildInvoiceLineSchema(t))
      .min(INVOICE_LINES_MIN, t('invoiceEditor.errors.linesRequired'))
      .max(INVOICE_LINES_MAX, t('invoiceEditor.errors.linesMax')),
  })
}

export type InvoiceWriteFormValues = z.infer<ReturnType<typeof buildInvoiceWriteSchema>>

/** Details dialog: external number + date (required together), tag, deviation, internal note. */
export function buildInvoiceDetailsSchema(t: TFunction) {
  return z
    .object({
      external_number: z
        .string()
        .max(EXTERNAL_NUMBER_MAX, t('invoices.details.errors.externalNumberMax'))
        .nullable(),
      external_date: z.string().nullable(),
      tag: tagSchema,
      deviation: z.number(t('invoices.details.errors.deviationInvalid')).nullable(),
      internal_note: z.string().max(NOTES_MAX, t('invoices.details.errors.internalNoteMax')).nullable(),
    })
    .superRefine((values, ctx) => {
      if (values.external_number && !values.external_date) {
        ctx.addIssue({
          code: 'custom',
          path: ['external_date'],
          message: t('invoices.details.errors.externalDateRequired'),
        })
      }
    })
}

export type InvoiceDetailsFormValues = z.infer<ReturnType<typeof buildInvoiceDetailsSchema>>

/** Collection dialog: 0 < collected_amount <= the installment amount. */
export function buildInvoiceCollectionSchema(t: TFunction, installmentAmount: number) {
  return z.object({
    collected_amount: z
      .number(t('invoices.collection.errors.amountInvalid'))
      .gt(0, t('invoices.collection.errors.amountPositive'))
      .max(installmentAmount, t('invoices.collection.errors.amountExceeds')),
    collected_at: z.string().min(1, t('invoices.collection.errors.dateRequired')),
  })
}

export type InvoiceCollectionFormValues = z.infer<ReturnType<typeof buildInvoiceCollectionSchema>>
