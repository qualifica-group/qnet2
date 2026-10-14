import type { FieldPath } from 'react-hook-form'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import { NO_ID } from '@/features/invoices/invoice-editor-source'
import type { AvailableInvoiceLine, InvoiceWritePayload, VatRateRef } from '@/features/invoices/types'
import { documentAmountsPerRate, type DocumentAmounts } from '@/lib/amounts/line-amounts'

export type InvoiceLineValues = InvoiceWriteFormValues['lines'][number]
export type VatRefs = Record<number, VatRateRef>

export const DEFAULT_LINE_QUANTITY = 1

/** Quantity decimals of an issued invoice (spec 0253 D-5). */
export const INVOICE_QUANTITY_SCALE = 2

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

/** Per-row amounts and totals of the document, VAT computed once per rate (spec 0253 D-1). */
export function amountsOf(lines: readonly InvoiceLineValues[], vatRefs: VatRefs): DocumentAmounts {
  return documentAmountsPerRate(
    lines.map((line) => ({
      quantity: line.quantity,
      unitPrice: line.unit_price,
      vatRate: vatPercentOf(line.vat_rate_id, vatRefs),
    })),
    INVOICE_QUANTITY_SCALE,
  )
}

/** Empty notes travel as null; totals are never sent (the server recomputes them). */
export function buildWritePayload(values: InvoiceWriteFormValues): InvoiceWritePayload {
  return {
    ...values,
    notes: values.notes?.trim() ? values.notes : null,
    internal_note: values.internal_note?.trim() ? values.internal_note : null,
  }
}

const HEADER_FIELDS = [
  'document_date',
  'company_id',
  'customer_registry_id',
  'payment_method_id',
  'financial_account_id',
  'layout_id',
  'notes',
  'internal_note',
  'invoice_tag_id',
] as const
const LINE_FIELD_PATTERN = /^lines(\.\d+\.(quote_line_id|product_id|description|quantity|unit_price|vat_rate_id))?$/

/** True when a server error key maps onto a field of the form. */
export function isFormFieldPath(key: string): key is FieldPath<InvoiceWriteFormValues> {
  return (HEADER_FIELDS as readonly string[]).includes(key) || LINE_FIELD_PATTERN.test(key)
}
