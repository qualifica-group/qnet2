/**
 * Work Order Emails types (spec 0175 frozen `data_contract`, section
 * "Email della commessa"). Mirrors the backend shapes exactly — no field is
 * invented here; anything the composer needs beyond this contract is a gap to
 * report, not to guess.
 */

import type { EmailRecipientSuggestion } from '@/components/ui/email-recipients-input'

export type OutboundEmailStatus = 'draft' | 'queued' | 'sent' | 'failed'

/** One file copied onto the email (D-7): upload, documents, document bundle or quote PDF. */
export interface OutboundEmailAttachment {
  id: number
  original_name: string
  mime_type: string
  size: number
}

/** The actor who composed/sends the email (D-6): a snapshot, not a live user reference. */
export interface OutboundEmailSender {
  id: number
  name: string
}

/**
 * Single email as returned by GET/POST/PATCH
 * `/api/work-orders/{workOrder}/emails[/{email}]`. `can` mirrors the
 * per-instance authorization the backend already resolved (D-2/D-3): the
 * composer gates its own actions on it instead of re-deriving the rules.
 */
export interface OutboundEmail {
  id: number
  status: OutboundEmailStatus
  email_template_id: number | null
  sender: OutboundEmailSender
  /** `users.email` snapshot, valorized only at send (D-6); `null` on a draft. */
  from_address: string | null
  to: string[]
  cc: string[]
  bcc: string[]
  subject: string | null
  /** HTML sanitized with `RichTextSanitizer` (D-11): no `<img>`. */
  body: string | null
  attachments: OutboundEmailAttachment[]
  attachments_total_size: number
  queued_at: string | null
  sent_at: string | null
  failed_at: string | null
  error_message: string | null
  created_at: string
  updated_at: string
  can: { update: boolean; delete: boolean; send: boolean }
}

/** Row projection of the history list (`GET .../emails`). */
export interface OutboundEmailListItem {
  id: number
  status: OutboundEmailStatus
  sender: OutboundEmailSender
  to: string[]
  subject: string | null
  attachments_count: number
  sent_at: string | null
  failed_at: string | null
  updated_at: string
}

/** Laravel paginator metadata, as returned alongside `data` (not nested under it). */
export interface OutboundEmailListMeta {
  current_page: number
  last_page: number
  total: number
}

export type RecipientSuggestionSource = 'registry' | 'referent' | 'supervisor' | 'participant'

/** A pickable recipient (D-5), shape-compatible with `EmailRecipientsInput`'s own suggestion type. */
export interface RecipientSuggestion extends EmailRecipientSuggestion {
  source: RecipientSuggestionSource
}

export type ComposeContextDocumentSource = 'work_order' | 'registry'

/** One document eligible for the "Da documenti" import (D-7b): the commessa's own or its anagrafica's. */
export interface ComposeContextDocument {
  id: number
  original_name: string
  size: number
  source: ComposeContextDocumentSource
}

/**
 * `GET .../emails/compose-context` (D-5/D-6/D-7): everything the composer
 * needs besides the draft itself — sender identity, recipient/document pools
 * and the attachment size cap, resolved once server-side so the client never
 * re-derives visibility scoping.
 */
export interface ComposeContext {
  sender: { name: string; email: string }
  recipient_suggestions: RecipientSuggestion[]
  documents: ComposeContextDocument[]
  quote_pdf_available: boolean
  max_total_attachments_kb: number
}

/** `POST .../emails/render-template` result (D-4): placeholders already resolved, HTML-escaped in the body. */
export interface RenderTemplateResult {
  subject: string
  body: string
}

/** Shared shape of POST (create) and PATCH (update) — every field optional, a draft can be saved empty (D-2). */
export interface OutboundEmailPayload {
  email_template_id?: number | null
  to?: string[]
  cc?: string[]
  bcc?: string[]
  subject?: string | null
  body?: string | null
}

export type AttachmentImportSource = 'documents' | 'document_bundle' | 'quote_pdf'

/** `POST .../attachments/import` request (D-7). */
export interface ImportAttachmentsPayload {
  source: AttachmentImportSource
  attachment_ids?: number[]
  document_bundle_id?: number
}
