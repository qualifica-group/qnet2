/**
 * Purchase orders (ODA) types. Source of truth: the frozen `data_contract` of
 * spec 0210. Amounts travel as decimal strings ("123.40"), quantities as
 * 3-decimal strings ("1.000"); dates as ISO `Y-m-d`.
 */

export const PURCHASE_ORDERS_DOMAIN = 'purchase-orders'

/** `attachable_type` alias of `config/attachments.php` (spec 0210 D-14). */
export const PURCHASE_ORDER_ATTACHABLE = 'purchase_order'

/** The server enum is the single source of truth for the logic; the client only reads `abilities.transitions` (spec constraints). */
export const PURCHASE_ORDER_STATUSES = [
  'draft',
  'in_validation',
  'approved',
  'sent_to_supplier',
  'partially_fulfilled',
  'fulfilled',
  'cancelled',
] as const
export type PurchaseOrderStatus = (typeof PURCHASE_ORDER_STATUSES)[number]

/** Narrows an arbitrary value to the enum: names, colors, icons and order come from the catalog (spec 0248), never from here. */
export function isPurchaseOrderStatus(value: unknown): value is PurchaseOrderStatus {
  return typeof value === 'string' && (PURCHASE_ORDER_STATUSES as readonly string[]).includes(value)
}

/** Statuses in which header, lines, deletion and merge are allowed (spec 0210 D-10). */
export const EDITABLE_STATUSES: readonly PurchaseOrderStatus[] = ['draft', 'in_validation']

/** Bounds of the monthly copies at creation (D-16). */
export const MIN_COPIES = 1
export const MAX_COPIES = 12

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

export interface SupplierRef extends NamedRef {
  email: string | null
  is_qualified_supplier: boolean
}

export interface PurchaseOrderLine {
  id: number
  position: number
  product: NamedRef | null
  description: string
  /** `color` is the cost type's palette token (spec 0249 D-6). */
  cost_type: NamedRef & { color?: string | null }
  unit_of_measure: UnitOfMeasureRef | null
  quantity: string
  unit_price: string
  vat_rate: VatRateRef | null
  taxable_amount: string
  vat_amount: string
  total_amount: string
  work_order: WorkOrderRef | null
  /** Set only for lines generated from an RDA line (D-17); drives the "RDA #id" badge. */
  purchase_request_line: { id: number; purchase_request_id: number } | null
}

export interface PurchaseOrderAbilities {
  update: boolean
  delete: boolean
  merge: boolean
  send: boolean
  view_activity: boolean
  transitions: PurchaseOrderStatus[]
}

export interface OrderFieldPermission {
  visible: boolean
  editable: boolean
  required: boolean
}

export interface PurchaseOrder {
  id: number
  number: number
  year: number
  /** "{number}/{year}". */
  code: string
  status: PurchaseOrderStatus
  supplier: SupplierRef
  company: NamedRef
  company_site: NamedRef
  operational_site: NamedRef | null
  order_date: string
  description: string
  general_notes: string | null
  payment_method: NamedRef | null
  is_internal_user: boolean
  customer: NamedRef | null
  work_order: WorkOrderRef | null
  quote: NamedRef | null
  opportunity: NamedRef | null
  sectors: NamedRef[]
  sector_notes: string | null
  taxable_total: string
  vat_total: string
  grand_total: string
  copy_group_id: string | null
  created_by: NamedRef
  lines: PurchaseOrderLine[]
  merge_logs_count: number
  created_at: string
  updated_at: string
  abilities: PurchaseOrderAbilities
  field_permissions: Record<string, OrderFieldPermission>
}

export interface PurchaseOrderLinePayload {
  /** Present = existing line, absent = new line (omitted lines are deleted). */
  id?: number
  product_id: number | null
  description: string
  cost_type_id: number
  unit_of_measure_id: number | null
  quantity: number
  unit_price: number
  vat_rate_id: number | null
  work_order_id: number | null
}

export interface PurchaseOrderPayload {
  supplier_id: number
  company_id: number
  company_site_id: number
  operational_site_id: number | null
  order_date: string
  description: string
  general_notes: string | null
  payment_method_id: number | null
  is_internal_user: boolean
  customer_id: number | null
  work_order_id: number | null
  quote_id: number | null
  opportunity_id: number | null
  sector_ids: number[]
  sector_notes: string | null
  /** Creation only (1..12, D-16). */
  copies?: number
  lines: PurchaseOrderLinePayload[]
}

/** POST result: the first order (copies > 1) plus every created id (`meta.created_ids`). */
export interface CreatedPurchaseOrder {
  order: PurchaseOrder
  createdIds: number[]
}

export interface MergeCandidateLine {
  id: number
  description: string
  quantity: string
  unit_price: string
  taxable_amount: string
  total_amount: string
}

export interface MergeCandidate {
  id: number
  code: string
  order_date: string
  description: string
  status: PurchaseOrderStatus
  grand_total: string
  lines: MergeCandidateLine[]
}

/** POST merge result: the updated destination plus the sources emptied and deleted (`meta.deleted_purchase_order_ids`). */
export interface MergeResult {
  order: PurchaseOrder
  deletedIds: number[]
}

export type MergeAction = 'deleted' | 'partial'

export interface MergeLogLine {
  description: string
  quantity: string
  unit_price: string
  taxable_amount: string
  total_amount: string
}

export interface MergeLog {
  id: number
  source_purchase_order_id: number | null
  source_code: string
  source_action: MergeAction
  source_description: string
  source_order_date: string
  source_grand_total: string
  moved_lines: MergeLogLine[]
  performed_by: NamedRef
  created_at: string
}
