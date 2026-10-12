import axios from 'axios'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'
import type { NamedRef } from '@/features/purchase-requests/types'

/** Permission that lets the actor create an ODA (spec 0210). */
export const GENERATE_ORDERS_PERMISSION = 'purchase-orders.create'
/** Only approved lines can become an ODA (D-17). */
const ORDERABLE_STATUS = 'approved'

/** Why "Generate ODA" is unavailable for a selection, as the suffix of `purchaseRequests.generateOrders.blocked.*`. */
export type GenerateOrdersBlocker = 'permission' | 'status' | 'supplier' | 'linked'

/** First reason the selection cannot generate ODAs, or null when it can (the server stays the authority). */
export function generateOrdersBlocker(
  targets: readonly LineTarget[],
  canCreateOrders: boolean,
): GenerateOrdersBlocker | null {
  if (!canCreateOrders) return 'permission'
  if (targets.some((target) => target.status !== ORDERABLE_STATUS)) return 'status'
  if (targets.some((target) => target.supplier === null)) return 'supplier'
  if (targets.some((target) => target.purchaseOrder !== null)) return 'linked'
  return null
}

export interface SupplierGroup {
  supplier: NamedRef
  lines: LineTarget[]
}

/** Selected lines grouped by supplier, in first-seen order: the preview of the ODAs that will be created. */
export function groupBySupplier(targets: readonly LineTarget[]): SupplierGroup[] {
  const groups = new Map<number, SupplierGroup>()
  for (const target of targets) {
    if (target.supplier === null) continue
    const group = groups.get(target.supplier.id)
    if (group) {
      group.lines.push(target)
    } else {
      groups.set(target.supplier.id, { supplier: target.supplier, lines: [target] })
    }
  }
  return [...groups.values()]
}

/** Every message of a 422 (`line_ids.N` ...) or, failing that, the envelope message of the rejection. */
export function generateOrdersErrors(error: unknown): string[] {
  const errors = axios.isAxiosError(error)
    ? (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors
    : undefined
  const messages = Object.values(errors ?? {}).flatMap((list) => list.slice(0, 1))
  if (messages.length > 0) return messages
  const message = serverMessage(error)
  return message === null ? [] : [message]
}
