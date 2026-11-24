import type { DocumentRequest, DocumentRequestStatus } from '@/features/document-requests/types'

const TERMINAL_STATUSES: readonly DocumentRequestStatus[] = ['completed', 'expired', 'cancelled']
const REMINDABLE_STATUSES: readonly DocumentRequestStatus[] = ['sent', 'in_progress']

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
  }
}

export function hasPendingDelivery(requests: readonly DocumentRequest[] | undefined): boolean {
  return requests?.some((request) => request.delivery_status === 'pending') ?? false
}
