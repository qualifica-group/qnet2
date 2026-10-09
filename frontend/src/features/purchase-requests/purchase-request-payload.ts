import type {
  PurchaseRequest,
  PurchaseRequestLine,
  PurchaseRequestLinePayload,
  PurchaseRequestPayload,
} from '@/features/purchase-requests/types'
import { PENDING_STATUS } from '@/features/purchase-requests/types'
import type {
  PurchaseRequestFormValues,
  PurchaseRequestLineFormValues,
} from '@/features/purchase-requests/purchase-request-schema'

const DEFAULT_PRIORITY = 'medium'
const DEFAULT_QUANTITY = 1

/** Today as ISO `Y-m-d` in local time (the default of the request date). */
export function todayIso(now: Date = new Date()): string {
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

/** A new, editable line (always `pending_approval` server-side, D-10). */
export function newLineValues(overrides: Partial<PurchaseRequestLineFormValues> = {}): PurchaseRequestLineFormValues {
  return {
    status: PENDING_STATUS,
    locked: false,
    can_delete: true,
    transitions: [],
    vat_rate_percent: null,
    pending_files: [],
    product_id: null,
    description: '',
    reason: '',
    unit_of_measure_id: null,
    quantity: DEFAULT_QUANTITY,
    unit_price: 0,
    vat_rate_id: null,
    ...overrides,
  }
}

export function emptyFormValues(requesterId: number | null, now: Date = new Date()): PurchaseRequestFormValues {
  return {
    subject: '',
    requested_at: todayIso(now),
    priority: DEFAULT_PRIORITY,
    requester_id: requesterId,
    function_manager_id: null,
    customer_id: null,
    supplier_id: null,
    work_order_id: null,
    company_id: null,
    company_site_id: null,
    operational_site_id: null,
    business_function_id: null,
    notes: '',
    delivery_terms: '',
    procurement_plan: '',
    technical_requirements: '',
    special_conditions: '',
    pending_files: [],
    lines: [newLineValues()],
  }
}

/** Only a `pending_approval` line the actor may update stays editable (D-10); a closed RDA locks everything. */
function lineToFormValues(line: PurchaseRequestLine, isClosed: boolean): PurchaseRequestLineFormValues {
  return {
    id: line.id,
    status: line.status,
    locked: isClosed || line.status !== PENDING_STATUS || !line.abilities.update,
    can_delete: line.abilities.delete && !isClosed,
    transitions: isClosed ? [] : line.abilities.transitions,
    vat_rate_percent: line.vat_rate ? Number(line.vat_rate.rate) : null,
    pending_files: [],
    product_id: line.product?.id ?? null,
    description: line.description,
    reason: line.reason ?? '',
    unit_of_measure_id: line.unit_of_measure?.id ?? null,
    quantity: Number(line.quantity),
    unit_price: Number(line.unit_price),
    vat_rate_id: line.vat_rate?.id ?? null,
  }
}

export function toFormValues(request: PurchaseRequest): PurchaseRequestFormValues {
  const isClosed = request.status === 'closed'
  return {
    subject: request.subject,
    requested_at: request.requested_at,
    priority: request.priority,
    requester_id: request.requester.id,
    function_manager_id: request.function_manager.id,
    customer_id: request.customer?.id ?? null,
    supplier_id: request.supplier?.id ?? null,
    work_order_id: request.work_order?.id ?? null,
    company_id: request.company.id,
    company_site_id: request.company_site.id,
    operational_site_id: request.operational_site.id,
    business_function_id: request.business_function.id,
    notes: request.notes ?? '',
    delivery_terms: request.delivery_terms ?? '',
    procurement_plan: request.procurement_plan ?? '',
    technical_requirements: request.technical_requirements ?? '',
    special_conditions: request.special_conditions ?? '',
    pending_files: [],
    lines: request.lines.map((line) => lineToFormValues(line, isClosed)),
  }
}

function blankToNull(value: string): string | null {
  return value.trim() === '' ? null : value
}

function toLinePayload(line: PurchaseRequestLineFormValues): PurchaseRequestLinePayload {
  return {
    ...(line.id === undefined ? {} : { id: line.id }),
    product_id: line.product_id,
    description: line.description,
    reason: blankToNull(line.reason),
    unit_of_measure_id: line.unit_of_measure_id,
    quantity: line.quantity,
    unit_price: line.unit_price,
    vat_rate_id: line.vat_rate_id,
  }
}

/**
 * Write payload (create and update share one shape, D contract): totals and
 * line statuses are never sent, the server owns them. Relation ids are
 * guaranteed non-null by the schema; the guard keeps the type honest.
 */
export function buildPayload(values: PurchaseRequestFormValues): PurchaseRequestPayload {
  const { requester_id, function_manager_id, company_id, company_site_id, operational_site_id, business_function_id } =
    values
  if (
    requester_id === null ||
    function_manager_id === null ||
    company_id === null ||
    company_site_id === null ||
    operational_site_id === null ||
    business_function_id === null
  ) {
    throw new Error('Required relation ids must be validated before building the payload')
  }
  return {
    subject: values.subject,
    requested_at: values.requested_at,
    priority: values.priority,
    requester_id,
    function_manager_id,
    customer_id: values.customer_id,
    supplier_id: values.supplier_id,
    work_order_id: values.work_order_id,
    company_id,
    company_site_id,
    operational_site_id,
    business_function_id,
    notes: blankToNull(values.notes),
    delivery_terms: blankToNull(values.delivery_terms),
    procurement_plan: blankToNull(values.procurement_plan),
    technical_requirements: blankToNull(values.technical_requirements),
    special_conditions: blankToNull(values.special_conditions),
    lines: values.lines.map(toLinePayload),
  }
}
