import type { DocumentRequest, DocumentRequestItem, DocumentRequestStatus } from '@/features/document-requests/types'

const TERMINAL_STATUSES: readonly DocumentRequestStatus[] = ['completed', 'expired', 'cancelled']
const REMINDABLE_STATUSES: readonly DocumentRequestStatus[] = ['sent', 'in_progress']

const APPROVED_ITEM_STATUSES: readonly string[] = ['ok', 'approved_manually']
const REJECTED_ITEM_STATUSES: readonly string[] = ['ko', 'rejected_manually']
/** A file still waiting for the AI: its validation can be stopped (spec 0432). */
const VALIDATING_ITEM_STATUSES: readonly string[] = ['uploaded', 'validating']
/** A decision by hand, dropped by a new AI validation. */
const MANUAL_ITEM_STATUSES: readonly string[] = ['approved_manually', 'rejected_manually']
/** Outcomes worth a new AI validation after the rules changed. */
const REVALIDATION_ITEM_STATUSES: readonly string[] = ['ko', 'review']

/** Poll period while a call to ArchivioSmart is still queued. */
export const PENDING_DELIVERY_REFETCH_MS = 5000

/** False once the request reached a final state (completed, expired, cancelled), or before ArchivioSmart answered. */
export function isOpen(request: DocumentRequest): boolean {
  return request.status === null || !TERMINAL_STATUSES.includes(request.status)
}

/** ArchivioSmart never answered and nothing is queued: it does not know the request (spec 0428, D-5). */
export function neverReachedArchivioSmart(request: DocumentRequest): boolean {
  return request.status === null && request.delivery_status !== 'pending'
}

/** Which actions a request offers: the actor's permission AND the states allowed by spec 0428. */
export function requestActions(request: DocumentRequest) {
  return {
    edit: request.permissions.update && isOpen(request),
    remind: request.permissions.remind && request.status !== null && REMINDABLE_STATUSES.includes(request.status),
    cancel: request.permissions.cancel && isOpen(request),
    refresh: request.permissions.update && !neverReachedArchivioSmart(request),
    complete: request.permissions.complete,
    refreshRules: request.permissions.refresh_rules,
  }
}

/**
 * Approve is offered unless the item is already approved; reject unless already rejected (spec 0429 AC-011).
 * Revalidate needs a file; stopping the validation needs a file still waiting for the AI (spec 0432).
 */
export function itemReviewActions(request: DocumentRequest, item: DocumentRequestItem) {
  const reviewable = request.permissions.review && item.current_document_id !== null
  return {
    approve: reviewable && !APPROVED_ITEM_STATUSES.includes(item.status),
    reject: reviewable && !REJECTED_ITEM_STATUSES.includes(item.status),
    revalidate: request.permissions.review && item.documents_count > 0,
    stopValidation: request.permissions.review && VALIDATING_ITEM_STATUSES.includes(item.status),
  }
}

/** True when a new AI validation drops a decision taken by hand on the item. */
export function hasManualDecision(item: DocumentRequestItem): boolean {
  return MANUAL_ITEM_STATUSES.includes(item.status)
}

/** Items changed by the last rules refresh that hold files rejected or to review: the ones to validate again. */
export function itemsToRevalidate(request: DocumentRequest): DocumentRequestItem[] {
  if (!request.permissions.review) {
    return []
  }
  const changed = new Set(request.rules_updated_item_ids)
  return request.items.filter(
    (item) => changed.has(Number(item.id)) && item.documents_count > 0 && REVALIDATION_ITEM_STATUSES.includes(item.status),
  )
}

export function hasPendingDelivery(requests: readonly DocumentRequest[] | undefined): boolean {
  return requests?.some((request) => request.delivery_status === 'pending') ?? false
}
