import type { TableRow } from '@/features/table/types'
import {
  LINE_CAPABILITIES,
  LINE_STATUSES,
  type LineCapability,
  type LineStatus,
  type LineSupplierAssignment,
  type NamedRef,
  type PurchaseOrderRef,
  type PurchaseRequestLine,
} from '@/features/purchase-requests/types'

/** A line selected for a status change: the summary the dialog shows plus the server-advertised abilities. */
export interface LineTarget {
  id: number
  /** Current status, shown preselected when a single line is changed. */
  status: LineStatus | null
  transitions: LineStatus[]
  /** Roles the actor holds on this line (informative, D-17). */
  capabilities: LineCapability[]
  description: string
  quantity: number
  unitSymbol: string | null
  totalAmount: number
  /** Current supplier of the line; ordering needs one (spec 0209 D-2). */
  supplier: NamedRef | null
  /** `abilities.update_supplier`: whether the supplier can still be assigned or changed (D-3). */
  canUpdateSupplier: boolean
  /** ODA the line is already linked to (spec 0210): such a line cannot generate another one. */
  purchaseOrder: PurchaseOrderRef | null
  purchaseRequestId: number | null
  purchaseRequestSubject: string | null
}

function isLineStatus(value: unknown): value is LineStatus {
  return typeof value === 'string' && (LINE_STATUSES as readonly string[]).includes(value)
}

function isLineCapability(value: unknown): value is LineCapability {
  return typeof value === 'string' && (LINE_CAPABILITIES as readonly string[]).includes(value)
}

function readSymbol(value: unknown): string | null {
  if (typeof value === 'string') {
    return value
  }
  const unit = value as { symbol?: unknown; name?: unknown } | null | undefined
  const label = unit?.symbol ?? unit?.name
  return typeof label === 'string' ? label : null
}

function readPurchaseOrder(value: unknown): PurchaseOrderRef | null {
  const order = value as { id?: unknown; code?: unknown } | null | undefined
  return typeof order?.id === 'number' && typeof order.code === 'string' ? { id: order.id, code: order.code } : null
}

function readText(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}

function readNamedRef(value: unknown): NamedRef | null {
  const ref = value as { id?: unknown; name?: unknown } | null | undefined
  return typeof ref?.id === 'number' && typeof ref.name === 'string' ? { id: ref.id, name: ref.name } : null
}

/** Reads the grid row of the lines table (`abilities.transitions`/`capabilities`/`update_supplier`, specs 0208 and 0209). */
export function toLineTarget(row: TableRow): LineTarget {
  const abilities = row.abilities as
    | { transitions?: unknown; capabilities?: unknown; update_supplier?: unknown }
    | null
    | undefined
  const requestId = row.purchase_request_id == null ? null : Number(row.purchase_request_id)
  return {
    id: Number(row.id),
    status: isLineStatus(row.status) ? row.status : null,
    transitions: Array.isArray(abilities?.transitions) ? abilities.transitions.filter(isLineStatus) : [],
    capabilities: Array.isArray(abilities?.capabilities) ? abilities.capabilities.filter(isLineCapability) : [],
    description: readText(row.description) ?? '',
    quantity: Number(row.quantity) || 0,
    unitSymbol: readSymbol(row.unit_of_measure),
    totalAmount: Number(row.total_amount) || 0,
    supplier: readNamedRef(row.supplier),
    canUpdateSupplier: abilities?.update_supplier === true,
    purchaseOrder: readPurchaseOrder(row.purchase_order),
    purchaseRequestId: requestId !== null && Number.isFinite(requestId) ? requestId : null,
    purchaseRequestSubject: readText(row.purchase_request_subject),
  }
}

/** Builds the target of a saved line of an RDA (detail panel, form); `transitions` overrides the advertised ones (closed RDA). */
export function lineToTarget(
  line: PurchaseRequestLine,
  request: { id: number; subject: string },
  transitions: LineStatus[] = line.abilities.transitions,
): LineTarget {
  return {
    id: line.id,
    status: line.status,
    transitions,
    capabilities: line.abilities.capabilities,
    description: line.description,
    quantity: Number(line.quantity),
    unitSymbol: line.unit_of_measure?.symbol ?? line.unit_of_measure?.name ?? null,
    totalAmount: Number(line.total_amount),
    supplier: line.supplier,
    canUpdateSupplier: line.abilities.update_supplier,
    purchaseOrder: line.purchase_order,
    purchaseRequestId: request.id,
    purchaseRequestSubject: request.subject,
  }
}

/**
 * Options of the dialog: the common transitions and, for a single line, its
 * current status too (preselected, but not a valid target). Canonical order.
 */
export function dialogOptions(targets: readonly LineTarget[]): { options: LineStatus[]; current: LineStatus | null } {
  const transitions = commonTransitions(targets)
  const current = targets.length === 1 ? targets[0].status : null
  if (transitions.length === 0 || current === null) {
    return { options: transitions, current: null }
  }
  return { options: LINE_STATUSES.filter((status) => status === current || transitions.includes(status)), current }
}

/**
 * Statuses every selected line can move to: the intersection of their
 * transitions (the client never derives the matrix, it only intersects what
 * the server advertised). Kept in the canonical status order.
 */
export function commonTransitions(targets: readonly LineTarget[]): LineStatus[] {
  if (targets.length === 0) {
    return []
  }
  return LINE_STATUSES.filter((status) => targets.every((target) => target.transitions.includes(status)))
}

/** Capabilities held on every selected line, in canonical order. */
export function commonCapabilities(targets: readonly LineTarget[]): LineCapability[] {
  if (targets.length === 0) {
    return []
  }
  return LINE_CAPABILITIES.filter((capability) => targets.every((target) => target.capabilities.includes(capability)))
}

/** The RDA every selected line belongs to, or null when they come from different ones (or it is unknown). */
export function sharedRequest(targets: readonly LineTarget[]): { id: number; subject: string } | null {
  const first = targets[0]
  if (!first || first.purchaseRequestId === null) {
    return null
  }
  return targets.every((target) => target.purchaseRequestId === first.purchaseRequestId)
    ? { id: first.purchaseRequestId, subject: first.purchaseRequestSubject ?? '' }
    : null
}

/** Status that needs a supplier on every line (spec 0209 D-2). */
export const SUPPLIER_REQUIRED_STATUS: LineStatus = 'ordered'

/** Statuses that need a reason from the actor (spec 0247 D-2); the server answers 422 on `reason` without one. */
export const REASON_REQUIRED_STATUSES: readonly LineStatus[] = ['rejected', 'on_hold']

/** Suppliers picked in the dialog, by line id; only lines without a supplier need one. */
export type PickedSuppliers = Readonly<Record<number, NamedRef>>

/** True while some selected line has neither its own supplier nor one picked in the dialog. */
export function hasMissingSupplier(targets: readonly LineTarget[], picked: PickedSuppliers): boolean {
  return targets.some((target) => target.supplier === null && picked[target.id] === undefined)
}

/** The `suppliers` payload: only the lines that had none and got one in the dialog. */
export function pickedAssignments(targets: readonly LineTarget[], picked: PickedSuppliers): LineSupplierAssignment[] {
  return targets.flatMap((target) => {
    const supplier = target.supplier === null ? picked[target.id] : undefined
    return supplier ? [{ line_id: target.id, supplier_id: supplier.id }] : []
  })
}
