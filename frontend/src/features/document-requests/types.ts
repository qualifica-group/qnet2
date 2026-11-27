/**
 * Document requests types (spec 0428 frozen `data_contract`): a request is a
 * child of a work order, sent to ArchivioSmart through a queued job. `status`
 * is the remote state (null until ArchivioSmart first answers); `delivery_*`
 * track the outbound call.
 */

import type { DocumentChecklistSyncOutcome } from '@/features/document-checklists/types'

export type DocumentRequestStatus = 'draft' | 'sent' | 'in_progress' | 'completed' | 'expired' | 'cancelled'
export type DocumentRequestDeliveryStatus = 'pending' | 'delivered' | 'failed'
export type DocumentRequestOperation =
  | 'upsert'
  | 'remind'
  | 'cancel'
  | 'refresh'
  | 'approve'
  | 'reject'
  | 'complete'
  | 'refresh_rules'
  | 'revalidate'
  | 'stop_validation'

export interface DocumentRequestSection {
  name: string
  description: string | null
}

export interface DocumentRequestValidation {
  outcome: string
  reason_for_customer: string | null
  decided_by: string | null
  created_at: string | null
}

/** One file uploaded by the customer for an item (spec 0429; `sha256` is never exposed). */
export interface DocumentRequestDocument {
  id: number | string
  slot: number | null
  version: number | null
  is_current: boolean
  status: string
  status_reason: string | null
  original_name: string
  mime_type: string | null
  size_bytes: number | null
  created_at: string | null
}

/** Item of the request, copied from ArchivioSmart: `status` is its free remote vocabulary. */
export interface DocumentRequestItem {
  id: number | string
  section_index: number | null
  name: string
  description: string | null
  is_required: boolean
  min_files: number
  max_files: number
  status: string
  status_reason: string | null
  latest_validation: DocumentRequestValidation | null
  documents_count: number
  documents: DocumentRequestDocument[]
  current_document_id: number | string | null
}

export interface DocumentRequest {
  id: number
  work_order_id: number
  checklist: { id: number | null; code: string; name: string }
  category_name: string | null
  due_date: string | null
  recipient_email: string | null
  message: string | null
  status: DocumentRequestStatus | null
  title: string | null
  items_count: number
  items_uploaded_count: number
  items_satisfied_count: number
  sections: DocumentRequestSection[]
  items: DocumentRequestItem[]
  /** Items whose rules changed with the last refresh from the checklist (spec 0432). */
  rules_updated_item_ids: number[]
  sent_at: string | null
  last_reminded_at: string | null
  completed_at: string | null
  cancelled_at: string | null
  expired_at: string | null
  link_expires_at: string | null
  remote_updated_at: string | null
  delivery_status: DocumentRequestDeliveryStatus
  pending_operation: DocumentRequestOperation | null
  delivery_error: string | null
  created_at: string
  permissions: {
    update: boolean
    remind: boolean
    cancel: boolean
    review: boolean
    complete: boolean
    refresh_rules: boolean
  }
}

export interface DocumentRequestList {
  requests: DocumentRequest[]
  canCreate: boolean
}

export interface DocumentRequestDefaults {
  recipient_email: string | null
  customer_name: string | null
}

/** Body of POST /work-orders/{id}/document-requests. */
export interface CreateDocumentRequestPayload {
  document_checklist_id: number
  due_date: string | null
  recipient_email: string
  message: string | null
  send: boolean
}

/** Body of PUT /document-requests/{id}: only these fields are editable. */
export type UpdateDocumentRequestPayload = Omit<CreateDocumentRequestPayload, 'document_checklist_id'>

export interface DocumentRequestSyncLog {
  id: number
  operation: DocumentRequestOperation
  outcome: DocumentChecklistSyncOutcome
  http_status: number | null
  attempt: number
  message: string | null
  created_at: string
}

export type DocumentRequestReviewDecision = 'approve' | 'reject'

/** Input of the manual review of one item; the note is optional to approve, mandatory to reject. */
export interface ReviewDocumentRequestItemPayload {
  itemId: number | string
  decision: DocumentRequestReviewDecision
  note: string | null
}
