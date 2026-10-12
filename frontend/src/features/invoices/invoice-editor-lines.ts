import type { FieldPath } from 'react-hook-form'
import { computeInvoiceTotals, type InvoiceTotalsCents, type PreviewLine } from '@/features/invoices/invoice-amounts'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'
import { NO_ID } from '@/features/invoices/invoice-editor-source'
import type { AvailableInvoiceLine, InvoiceWritePayload, VatRateRef } from '@/features/invoices/types'

export type InvoiceLineValues = InvoiceFormValues['lines'][number]
export type VatRefs = Record<number, VatRateRef>

export const DEFAULT_LINE_QUANTITY = 1

/** Form row copied from a work-order line still available to be invoiced. */
export function lineFromAvailable(line: AvailableInvoiceLine): InvoiceLineValues {
  return {
    quote_line_id: line.quote_line_id,
    product_id: line.product.id,
    description: line.description,
    quantity: Number(line.quantity),
    unit_price: Number(line.unit_price),
    vat_rate_id: line.vat_rate?.id ?? NO_ID,
  }
}

/** New row typed by hand or picked from the catalog; VAT may still be unset (NO_ID). */
export type NewLineFields = Pick<InvoiceLineValues, 'description'> & Partial<InvoiceLineValues>

export function newLine(fields: NewLineFields): InvoiceLineValues {
  return {
    quote_line_id: null,
    product_id: null,
    quantity: DEFAULT_LINE_QUANTITY,
    unit_price: 0,
    vat_rate_id: NO_ID,
    ...fields,
  }
}

/** Percentage of the row's VAT rate, or null while none is selected or known. */
export function vatPercentOf(vatRateId: number, vatRefs: VatRefs): number | null {
  const rate = vatRefs[vatRateId]?.rate
  return rate === undefined ? null : Number(rate)
}

export function toPreviewLine(line: InvoiceLineValues, vatRefs: VatRefs): PreviewLine {
  return {
    quantity: Number.isFinite(line.quantity) ? line.quantity : 0,
    unitPrice: Number.isFinite(line.unit_price) ? line.unit_price : 0,
    vatRatePercent: vatPercentOf(line.vat_rate_id, vatRefs),
  }
}

export function totalsOf(lines: readonly InvoiceLineValues[], vatRefs: VatRefs): InvoiceTotalsCents {
  return computeInvoiceTotals(lines.map((line) => toPreviewLine(line, vatRefs)))
}

/** Blank text travels as null: an empty note is no note. */
export function blankToNull(value: string | null): string | null {
  return value?.trim() ? value : null
}

/**
 * POST/PUT body: the write keys only (the registration fields go through PATCH
 * details); totals are never sent (the server recomputes them).
 */
export function buildWritePayload(values: InvoiceFormValues): InvoiceWritePayload {
  return {
    document_date: values.document_date,
    company_id: values.company_id,
    customer_registry_id: values.customer_registry_id,
    payment_method_id: values.payment_method_id,
    financial_account_id: values.financial_account_id,
    layout_id: values.layout_id,
    notes: blankToNull(values.notes),
    internal_note: blankToNull(values.internal_note),
    tag: values.tag,
    lines: values.lines,
  }
}

const FORM_FIELDS = [
  'document_date',
  'company_id',
  'customer_registry_id',
  'payment_method_id',
  'financial_account_id',
  'layout_id',
  'notes',
  'internal_note',
  'tag',
  'external_number',
  'external_date',
  'deviation',
] as const
const LINE_FIELD_PATTERN = /^lines(\.\d+\.(quote_line_id|product_id|description|quantity|unit_price|vat_rate_id))?$/

/** True when a server error key maps onto a field of the form. */
export function isFormFieldPath(key: string): key is FieldPath<InvoiceFormValues> {
  return (FORM_FIELDS as readonly string[]).includes(key) || LINE_FIELD_PATTERN.test(key)
}
