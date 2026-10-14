import { todayIso } from '@/features/purchase-requests/purchase-request-payload'
import {
  MIN_COPIES,
  type PurchaseOrder,
  type PurchaseOrderLine,
  type PurchaseOrderLinePayload,
  type PurchaseOrderPayload,
} from '@/features/purchase-orders/types'
import type {
  PurchaseOrderFormValues,
  PurchaseOrderLineFormValues,
} from '@/features/purchase-orders/purchase-order-schema'

const DEFAULT_QUANTITY = 1

/** A new, editable line; `overrides` carries the VAT/work order it inherits. */
export function newOrderLineValues(overrides: Partial<PurchaseOrderLineFormValues> = {}): PurchaseOrderLineFormValues {
  return {
    purchase_request_id: null,
    vat_rate_percent: null,
    product_id: null,
    description: '',
    cost_type_id: null,
    unit_of_measure_id: null,
    quantity: DEFAULT_QUANTITY,
    unit_price: 0,
    vat_rate_id: null,
    work_order_id: null,
    ...overrides,
  }
}

export function emptyOrderFormValues(now: Date = new Date()): PurchaseOrderFormValues {
  return {
    supplier_id: null,
    supplier_qualified: true,
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    order_date: todayIso(now),
    description: '',
    general_notes: '',
    payment_method_id: null,
    is_internal_user: false,
    customer_id: null,
    work_order_id: null,
    quote_id: null,
    opportunity_id: null,
    sector_ids: [],
    sector_notes: '',
    copies: MIN_COPIES,
    pending_files: [],
    lines: [newOrderLineValues()],
  }
}

function lineToFormValues(line: PurchaseOrderLine): PurchaseOrderLineFormValues {
  return {
    id: line.id,
    purchase_request_id: line.purchase_request_line?.purchase_request_id ?? null,
    vat_rate_percent: line.vat_rate ? Number(line.vat_rate.rate) : null,
    product_id: line.product?.id ?? null,
    description: line.description,
    cost_type_id: line.cost_type.id,
    unit_of_measure_id: line.unit_of_measure?.id ?? null,
    quantity: Number(line.quantity),
    unit_price: Number(line.unit_price),
    vat_rate_id: line.vat_rate?.id ?? null,
    work_order_id: line.work_order?.id ?? null,
  }
}

export function toOrderFormValues(order: PurchaseOrder): PurchaseOrderFormValues {
  return {
    supplier_id: order.supplier.id,
    supplier_qualified: order.supplier.is_qualified_supplier,
    company_id: order.company.id,
    company_site_id: order.company_site.id,
    operational_site_id: order.operational_site?.id ?? null,
    order_date: order.order_date,
    description: order.description,
    general_notes: order.general_notes ?? '',
    payment_method_id: order.payment_method?.id ?? null,
    is_internal_user: order.is_internal_user,
    customer_id: order.customer?.id ?? null,
    work_order_id: order.work_order?.id ?? null,
    quote_id: order.quote?.id ?? null,
    opportunity_id: order.opportunity?.id ?? null,
    sector_ids: order.sectors.map((sector) => sector.id),
    sector_notes: order.sector_notes ?? '',
    copies: MIN_COPIES,
    pending_files: [],
    lines: order.lines.map(lineToFormValues),
  }
}

function blankToNull(value: string): string | null {
  return value.trim() === '' ? null : value
}

function toLinePayload(line: PurchaseOrderLineFormValues): PurchaseOrderLinePayload {
  if (line.cost_type_id === null) {
    throw new Error('The cost type must be validated before building the payload')
  }
  return {
    ...(line.id === undefined ? {} : { id: line.id }),
    product_id: line.product_id,
    description: line.description,
    cost_type_id: line.cost_type_id,
    unit_of_measure_id: line.unit_of_measure_id,
    quantity: line.quantity,
    unit_price: line.unit_price,
    vat_rate_id: line.vat_rate_id,
    work_order_id: line.work_order_id,
  }
}

type OrderHeaderPayload = Omit<PurchaseOrderPayload, 'lines' | 'copies'>

/**
 * The header part of the write payload; totals are never sent, the server
 * owns them (D-12). Required ids are guaranteed non-null by the schema; the
 * guard keeps the type honest.
 */
function headerPayload(values: PurchaseOrderFormValues): OrderHeaderPayload {
  const { supplier_id, company_id, company_site_id } = values
  if (supplier_id === null || company_id === null || company_site_id === null) {
    throw new Error('Required relation ids must be validated before building the payload')
  }
  return {
    supplier_id,
    company_id,
    company_site_id,
    operational_site_id: values.operational_site_id,
    order_date: values.order_date,
    description: values.description,
    general_notes: blankToNull(values.general_notes),
    payment_method_id: values.payment_method_id,
    is_internal_user: values.is_internal_user,
    customer_id: values.customer_id,
    work_order_id: values.work_order_id,
    quote_id: values.quote_id,
    opportunity_id: values.opportunity_id,
    sector_ids: values.sector_ids,
    sector_notes: blankToNull(values.sector_notes),
  }
}

/** Create payload of the ODA: the whole header, the monthly copies (D-16) and every line. */
export function buildOrderPayload(values: PurchaseOrderFormValues): PurchaseOrderPayload {
  return { ...headerPayload(values), copies: values.copies, lines: buildOrderLinesPayload(values.lines) }
}

/** The `lines` of a partial update: always the whole set, the server deletes what is missing (spec 0257 D-1). */
export function buildOrderLinesPayload(lines: PurchaseOrderLineFormValues[]): PurchaseOrderLinePayload[] {
  return lines.map(toLinePayload)
}

/**
 * The header keys an in-place edit changed against the saved ODA (spec 0257
 * D-6): an untouched key is never sent. Sector ids compare as a set.
 */
export function buildOrderHeaderPatch(
  values: PurchaseOrderFormValues,
  persisted: PurchaseOrderFormValues,
): Partial<OrderHeaderPayload> {
  const next = headerPayload(values)
  const saved = headerPayload(persisted)
  const same = (key: keyof OrderHeaderPayload) =>
    key === 'sector_ids'
      ? [...next.sector_ids].sort().join(',') === [...saved.sector_ids].sort().join(',')
      : next[key] === saved[key]
  const keys = Object.keys(next) as (keyof OrderHeaderPayload)[]
  return Object.fromEntries(keys.filter((key) => !same(key)).map((key) => [key, next[key]]))
}
