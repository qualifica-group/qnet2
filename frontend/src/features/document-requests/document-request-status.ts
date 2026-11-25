import type { DocumentRequest, DocumentRequestItem, DocumentRequestStatus } from '@/features/document-requests/types'

const TERMINAL_STATUSES: readonly DocumentRequestStatus[] = ['completed', 'expired', 'cancelled']
const REMINDABLE_STATUSES: readonly DocumentRequestStatus[] = ['sent', 'in_progress']

const APPROVED_ITEM_STATUSES: readonly string[] = ['ok', 'approved_manually']
const REJECTED_ITEM_STATUSES: readonly string[] = ['ko', 'rejected_manually']

/** Poll period while a call to ArchivioSmart is still queued. */
export const PENDING_DELIVERY_REFETCH_MS = 5000

function isOpen(request: DocumentRequest): boolean {
  return request.status === null || !TERMINAL_STATUSES.includes(request.status)
}

/** Which actions a request offers: the actor's permission AND the states allowed by spec 0428. */
export function requestActions(request: DocumentRequest) {
  return {
    edit: request.permissions.update && isOpen(request),
    remind: request.permissions.remind && request.status !== null && REMINDABLE_STATUSES.includes(request.status),
    cancel: request.permissions.cancel && isOpen(request),
    refresh: request.permissions.update,
    complete: request.permissions.complete,
  }
}

/** Approve is offered unless the item is already approved; reject unless already rejected (spec 0429 AC-011). */
export function itemReviewActions(request: DocumentRequest, item: DocumentRequestItem) {
  const reviewable = request.permissions.review && item.current_document_id !== null
  return {
    approve: reviewable && !APPROVED_ITEM_STATUSES.includes(item.status),
    reject: reviewable && !REJECTED_ITEM_STATUSES.includes(item.status),
  }
}

export function hasPendingDelivery(requests: readonly DocumentRequest[] | undefined): boolean {
  return requests?.some((request) => request.delivery_status === 'pending') ?? false
}
