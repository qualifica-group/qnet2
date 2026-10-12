/**
 * Purchase requests (RDA) types. Source of truth: the frozen `data_contract`
 * of spec 0208. Amounts travel as decimal strings ("123.40"), quantities as
 * 3-decimal strings ("1.000"); dates as ISO `Y-m-d`.
 */

export const PURCHASE_REQUESTS_DOMAIN = 'purchase-requests'
export const PURCHASE_REQUEST_LINES_DOMAIN = 'purchase-request-lines'

/** `attachable_type` aliases of `config/attachments.php` (spec 0208 D-15). */
export const PURCHASE_REQUEST_ATTACHABLE = 'purchase_request'
export const PURCHASE_REQUEST_LINE_ATTACHABLE = 'purchase_request_line'

export const LINE_STATUSES = [
  'pending_approval',
  'approved',
  'ordered',
  'received',
  'rejected',
  'on_hold',
] as const
export type LineStatus = (typeof LINE_STATUSES)[number]

export const PRIORITIES = ['low', 'medium', 'high', 'urgent', 'critical'] as const
export type PurchaseRequestPriority = (typeof PRIORITIES)[number]

export type PurchaseRequestStatus = 'open' | 'closed'

export const PENDING_STATUS: LineStatus = 'pending_approval'

export interface NamedRef {
  id: number
  name: string
}

export interface WorkOrderRef {
  id: number
  code: string
  title: string | null
}

export interface UnitOfMeasureRef extends NamedRef {
  symbol: string
}

export interface VatRateRef extends NamedRef {
  rate: string
}

/** Roles the actor holds on a line (spec 0208 D-17); informative only, the server authorizes. */
export const LINE_CAPABILITIES = ['approve', 'fulfill', 'manage'] as const
export type LineCapability = (typeof LINE_CAPABILITIES)[number]

export interface PurchaseRequestLineAbilities {
  update: boolean
  delete: boolean
  transitions: LineStatus[]
  capabilities: LineCapability[]
  /** Server rule for the supplier freeze (spec 0209 D-3); the client never derives it. */
  update_supplier: boolean
}

/** ODA a purchase-order line is linked to (spec 0210 D-17), enough for the link of the ODA column. */
export interface PurchaseOrderRef {
  id: number
  code: string
}

export interface PurchaseRequestLine {
  id: number
  position: number
  product: NamedRef | null
  description: string
  reason: string | null
  unit_of_measure: UnitOfMeasureRef | null
  quantity: string
  unit_price: string
  vat_rate: VatRateRef | null
  supplier: NamedRef | null
  taxable_amount: string
  vat_amount: string
  total_amount: string
  status: LineStatus
  approved_by: NamedRef | null
  approved_at: string | null
  oda_reference: string | null
  purchase_order: PurchaseOrderRef | null
  abilities: PurchaseRequestLineAbilities
}

export interface PurchaseRequestAbilities {
  update: boolean
  delete: boolean
  close: boolean
  notify_manager: boolean
  view_activity: boolean
}

export interface RequestFieldPermission {
  visible: boolean
  editable: boolean
  required: boolean
}

export type LineStatusCounts = Partial<Record<LineStatus, number>>

export interface PurchaseRequest {
  id: number
  subject: string
  requested_at: string
  priority: PurchaseRequestPriority
  requester: NamedRef
  function_manager: NamedRef
  customer: NamedRef | null
  work_order: WorkOrderRef | null
  company: NamedRef
  company_site: NamedRef
  operational_site: NamedRef
  business_function: NamedRef
  created_by: NamedRef
  notes: string | null
  delivery_terms: string | null
  procurement_plan: string | null
  technical_requirements: string | null
  special_conditions: string | null
  taxable_total: string
  vat_total: string
  grand_total: string
  status: PurchaseRequestStatus
  closed_by: NamedRef | null
  closed_at: string | null
  close_reason: string | null
  line_status_counts: LineStatusCounts
  lines: PurchaseRequestLine[]
  created_at: string
  updated_at: string
  abilities: PurchaseRequestAbilities
  field_permissions: Record<string, RequestFieldPermission>
}

export interface PurchaseRequestLinePayload {
  /** Present = existing line, absent = new line (spec: `lines.*.id`). */
  id?: number
  product_id: number | null
  description: string
  reason: string | null
  unit_of_measure_id: number | null
  quantity: number
  unit_price: number
  vat_rate_id: number | null
  supplier_id: number | null
}

export interface PurchaseRequestPayload {
  subject: string
  requested_at: string
  priority: PurchaseRequestPriority
  requester_id: number
  function_manager_id: number
  customer_id: number | null
  work_order_id: number | null
  company_id: number
  company_site_id: number
  operational_site_id: number
  business_function_id: number
  notes: string | null
  delivery_terms: string | null
  procurement_plan: string | null
  technical_requirements: string | null
  special_conditions: string | null
  lines: PurchaseRequestLinePayload[]
}

/** Supplier given in the status dialog for a line without one; only valid with `to_status = ordered` (spec 0209 D-2). */
export interface LineSupplierAssignment {
  line_id: number
  supplier_id: number
}

export interface LineStatusChangePayload {
  line_ids: number[]
  to_status: LineStatus
  reason?: string
  suppliers?: LineSupplierAssignment[]
}

export interface LineSupplierPayload {
  line_ids: number[]
  supplier_id: number
}

export interface LineSupplierResult {
  updated_count: number
}

export interface LineStatusChangeResult {
  updated_count: number
  closed_purchase_request_ids: number[]
}

export interface LineStatusLog {
  id: number
  user: NamedRef
  from_status: LineStatus | null
  to_status: LineStatus
  reason: string | null
  is_bulk: boolean
  created_at: string
}

export interface PurchaseRequestClosure {
  can_close: boolean
  is_forced: boolean
  non_terminal_count: number
  line_status_counts: LineStatusCounts
}

/** Body of the bulk "Generate ODA" (spec 0210 D-17); `order_date` defaults to today server-side. */
export interface GenerateOrdersPayload {
  line_ids: number[]
  cost_type_id: number
  payment_method_id?: number
  order_date?: string
}

export interface GeneratedOrder {
  id: number
  code: string
  supplier: NamedRef
  lines_count: number
}

export interface GenerateOrdersResult {
  purchase_orders: GeneratedOrder[]
  closed_purchase_request_ids: number[]
}
