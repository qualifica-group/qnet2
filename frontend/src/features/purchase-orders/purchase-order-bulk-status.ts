import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'

/** Statuses that can be set by hand in bulk; fulfilment statuses follow the receipts (spec 0433 D-2). */
export const BULK_TARGET_STATUSES: readonly PurchaseOrderStatus[] = [
  'draft',
  'in_validation',
  'approved',
  'sent_to_supplier',
  'cancelled',
]

/** Maximum orders per request (spec 0433 D-3, `purchase-orders.bulk_status_max` server-side). */
export const BULK_STATUS_MAX_IDS = 200

export interface BulkStatusPayload {
  ids: number[]
  to_status: PurchaseOrderStatus
  dry_run: boolean
}

export interface BulkStatusChanged {
  id: number
  code: string
}

export interface BulkStatusSkipped extends BulkStatusChanged {
  reason: string
}

/** `changed` lists the orders that would pass when `applied` is false (dry run). */
export interface BulkStatusResult {
  applied: boolean
  to_status: string
  total: number
  changed: BulkStatusChanged[]
  skipped: BulkStatusSkipped[]
}
