import axios from 'axios'
import type { FieldPath, UseFormSetError } from 'react-hook-form'
import type { VatRateRef } from '@/features/invoices/types'
import type { VatRefs } from '@/features/invoices/use-vat-refs'
import type {
  PurchaseInvoiceLineFormValues,
  PurchaseInvoiceWriteFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'
import type {
  PurchaseInvoiceLine,
  PurchaseInvoiceLineWrite,
  PurchaseInvoiceWritePayload,
} from '@/features/purchase-invoices/types'
import { documentAmountsPerRate, type DocumentAmounts } from '@/lib/amounts/line-amounts'

export type { VatRefs }

/** Form sentinel of an unselected required id (zod `z.number()` rejects NaN with the field message). */
export const NO_ID = Number.NaN

export const DEFAULT_LINE_QUANTITY = 1

/** Quantity decimals of a purchase invoice (spec 0253 D-5). */
export const PURCHASE_INVOICE_QUANTITY_SCALE = 3

export function idToSelectValue(id: number): number | null {
  return Number.isNaN(id) ? null : id
}

/** Header defaults proposed to every row added afterwards. */
export interface LineDefaults {
  cost_type_id: number | null
  work_order_id: number | null
}

/**
 * New free or catalog row; the header cost type and work order are only a
 * proposal. A catalog product's default cost type (spec 0251 D-4) applies only
 * when the row has no cost type from the header yet.
 */
export function newLine(
  fields: Partial<PurchaseInvoiceLineFormValues> & Pick<PurchaseInvoiceLineFormValues, 'description'>,
  defaults: LineDefaults,
): PurchaseInvoiceLineFormValues {
  return {
    id: null,
    purchase_order_line_id: null,
    product_id: null,
    quantity: DEFAULT_LINE_QUANTITY,
    unit_price: 0,
    vat_rate_id: null,
    work_order_id: defaults.work_order_id,
    available_quantity: null,
    ...fields,
    cost_type_id: defaults.cost_type_id ?? fields.cost_type_id ?? NO_ID,
  }
}

/** Form row of a draft line (from an ODA): no persisted id yet. */
export function lineFromDraft(line: Omit<PurchaseInvoiceLine, 'id'>): PurchaseInvoiceLineFormValues {
  return {
    id: null,
    purchase_order_line_id: line.purchase_order_line_id,
    product_id: line.product?.id ?? null,
    description: line.description,
    quantity: Number(line.quantity),
    unit_price: Number(line.unit_price),
    vat_rate_id: line.vat_rate?.id ?? null,
    cost_type_id: line.cost_type.id,
    work_order_id: line.work_order?.id ?? null,
    available_quantity: line.available_quantity === undefined ? null : Number(line.available_quantity),
  }
}

/** Form row of a persisted line (edit). */
export function lineFromInvoice(line: PurchaseInvoiceLine): PurchaseInvoiceLineFormValues {
  return { ...lineFromDraft(line), id: line.id }
}

export function vatRefsOf(lines: Array<{ vat_rate: VatRateRef | null }>): VatRefs {
  return Object.fromEntries(lines.flatMap((line) => (line.vat_rate === null ? [] : [[line.vat_rate.id, line.vat_rate]])))
}

/** Percentage of the row's VAT rate; null (no VAT, or rate not known yet) counts as 0. */
export function vatPercentOf(vatRateId: number | null, vatRefs: VatRefs): number | null {
  if (vatRateId === null) {
    return null
  }
  const rate = vatRefs[vatRateId]?.rate
  return rate === undefined ? null : Number(rate)
}

/** Per-row amounts and header totals, VAT computed once per rate (client preview; the server recomputes). */
export function amountsOf(lines: readonly PurchaseInvoiceLineFormValues[], vatRefs: VatRefs): DocumentAmounts {
  return documentAmountsPerRate(
    lines.map((line) => ({
      quantity: line.quantity,
      unitPrice: line.unit_price,
      vatRate: vatPercentOf(line.vat_rate_id, vatRefs),
    })),
    PURCHASE_INVOICE_QUANTITY_SCALE,
  )
}

/** One row as the write endpoints take it. */
export function toLineWrite(line: PurchaseInvoiceLineFormValues): PurchaseInvoiceLineWrite {
  // available_quantity is a client-side limit only: the server recomputes it, so it is not sent.
  const write: PurchaseInvoiceLineWrite = {
    purchase_order_line_id: line.purchase_order_line_id,
    product_id: line.product_id,
    description: line.description,
    quantity: line.quantity,
    unit_price: line.unit_price,
    vat_rate_id: line.vat_rate_id,
    cost_type_id: line.cost_type_id,
    work_order_id: line.work_order_id,
  }
  return line.id === null ? write : { id: line.id, ...write }
}

/** Create payload: empty notes travel as null, totals are never sent, the number only for an invoice. */
export function buildWritePayload(values: PurchaseInvoiceWriteFormValues): PurchaseInvoiceWritePayload {
  const { document_type, document_number, lines, notes, ...header } = values
  return {
    ...header,
    notes: notes?.trim() ? notes : null,
    lines: lines.map(toLineWrite),
    document_type,
    document_number: document_type === 'invoice' ? (document_number?.trim() ?? null) : null,
  }
}

type FormPath = FieldPath<PurchaseInvoiceWriteFormValues>

const UNPROCESSABLE_STATUS = 422

const HEADER_FIELDS = [
  'document_type',
  'document_number',
  'document_date',
  'company_id',
  'supplier_id',
  'payment_method_id',
  'financial_account_id',
  'company_site_id',
  'operational_site_id',
  'cost_type_id',
  'work_order_id',
  'notes',
  'lines',
] as const
const LINE_ERROR_PATTERN = /^lines\.(\d+)\.(purchase_order_line_id|product_id|description|quantity|unit_price|vat_rate_id|cost_type_id|work_order_id)$/

/** Server 422 messages split into the ones that land on an input and the ones to list in the form. */
export interface MappedServerErrors {
  fields: Array<{ path: FormPath; message: string }>
  general: string[]
}

/**
 * Maps server error keys onto form paths. An already-invoiced ODA row
 * (`lines.N.purchase_order_line_id`) has no input of its own, so its message is
 * shown on the row's description.
 */
export function mapServerErrors(errors: Record<string, string[]>): MappedServerErrors {
  const mapped: MappedServerErrors = { fields: [], general: [] }
  for (const [key, messages] of Object.entries(errors)) {
    const message = messages[0]
    if (!message) continue
    const line = LINE_ERROR_PATTERN.exec(key)
    if (line) {
      const field = line[2] === 'purchase_order_line_id' ? 'description' : line[2]
      mapped.fields.push({ path: `lines.${line[1]}.${field}` as FormPath, message })
    } else if ((HEADER_FIELDS as readonly string[]).includes(key)) {
      mapped.fields.push({ path: key as FormPath, message })
    } else {
      mapped.general.push(message)
    }
  }
  return mapped
}

/**
 * Puts a 422's messages on their inputs (editor and record); the ones no
 * input carries come back, null when the failure is not a 422.
 */
export function applyPurchaseInvoiceServerErrors(
  error: unknown,
  setError: UseFormSetError<PurchaseInvoiceWriteFormValues>,
): string[] | null {
  if (!axios.isAxiosError(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const { fields, general } = mapServerErrors(
    (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {},
  )
  fields.forEach(({ path, message }) => setError(path, { message }))
  return general
}
