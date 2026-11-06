/**
 * Outbound Emails types (spec 0175 frozen `data_contract`, section
 * "Email della commessa"). Mirrors the backend shapes exactly — no field is
 * invented here; anything the composer needs beyond this contract is a gap to
 * report, not to guess.
 */

import type { EmailTemplateModule } from '@/features/email-templates/types'
import type { EmailRecipientSuggestion } from '@/components/ui/email-recipients-input'
import type { TaskRecurrenceFrequency, TaskRecurrenceMonthMode } from '@/features/tasks/task-recurrence-types'

/** Parent record an email thread hangs off: the URL segment is the REST resource name (spec 0195 D-10/D-14). */
export type EmailOwnerRef = { type: 'work-orders' | 'invoices' | 'purchase-orders'; id: number }

/** Email-template module whose templates/placeholders an owner uses (`GET /email-templates*?module=`). */
export const EMAIL_OWNER_TEMPLATE_MODULE: Record<EmailOwnerRef['type'], EmailTemplateModule> = {
  'work-orders': 'work_orders',
  invoices: 'invoices',
  'purchase-orders': 'purchase_orders',
}

export type OutboundEmailPurpose = 'document' | 'reminder'

export type OutboundEmailStatus = 'draft' | 'queued' | 'sent' | 'failed' | 'scheduled' | 'schedule_ended'

/** Why a recurring series stopped on its own (spec 0362, D-7). */
export type OutboundEmailRecurrenceEndReason = 'ends_on_reached' | 'owner_closed' | 'sender_unavailable'

/**
 * Recurrence rule + series state of a scheduled email (spec 0362
 * `OutboundEmailRecurrence`): not-pertinent rule fields come back as `null`.
 */
export interface OutboundEmailRecurrence {
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays: number[] | null
  month_mode: TaskRecurrenceMonthMode | null
  month_day: number | null
  ordinal: number | null
  ordinal_weekday: number | null
  year_month: number | null
  workdays_only: boolean
  starts_on: string
  ends_on: string
  /** `null` once the series has ended. */
  next_send_on: string | null
  last_sent_on: string | null
  /** Copies created so far, whatever their outcome. */
  sent_count: number
  end_reason: OutboundEmailRecurrenceEndReason | null
}

/**
 * POST `.../schedule` `recurrence` (spec 0362 `RecurrenceRuleInput`): fields
 * not pertinent to the picked frequency/month_mode are OMITTED (the server
 * prohibits them), `ends_on` is always required.
 */
export interface OutboundEmailRecurrenceInput {
  frequency: TaskRecurrenceFrequency
  interval: number
  weekdays?: number[]
  month_mode?: TaskRecurrenceMonthMode
  month_day?: number
  ordinal?: number
  ordinal_weekday?: number
  year_month?: number
  workdays_only: boolean
  starts_on: string
  ends_on: string
}

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
  /** `null` on work order emails and legacy rows (D-11). */
  purpose: OutboundEmailPurpose | null
  email_template_id: number | null
  sender: OutboundEmailSender
  /** Sender chosen on the draft (`null` = login email), the mailbox actually used from `queued` on (spec 0361). */
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
  /** `null` when the email never had a rule (spec 0362). */
  recurrence: OutboundEmailRecurrence | null
  /** On the copies a series sends: id of the scheduled email they come from. */
  recurrence_source_id: number | null
  can: { update: boolean; delete: boolean; send: boolean; schedule: boolean; unschedule: boolean }
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
  next_send_on: string | null
  is_recurring_copy: boolean
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
  /** Addresses the actor may send from (spec 0361, D-1): login email first, then the card's emails. */
  sender_options: string[]
  recipient_suggestions: RecipientSuggestion[]
  documents: ComposeContextDocument[]
  quote_pdf_available: boolean
  /** Default recipients (invoices: primary PEC, else email); absent on work orders, treat as `[]` (D-12). */
  default_to?: string[]
  /** Importable attachment sources this owner supports (e.g. `invoice_pdf`, `purchase_order_pdf`); absent on work orders. */
  sources?: string[]
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
  /** One of `ComposeContext.sender_options`; `null` = login email (spec 0361, D-2). */
  from_address?: string | null
  to?: string[]
  cc?: string[]
  bcc?: string[]
  subject?: string | null
  body?: string | null
}

export type AttachmentImportSource = 'documents' | 'document_bundle' | 'quote_pdf' | 'invoice_pdf' | 'purchase_order_pdf'

/** `POST .../attachments/import` request (D-7). */
export interface ImportAttachmentsPayload {
  source: AttachmentImportSource
  attachment_ids?: number[]
  document_bundle_id?: number
  /** Optional layout for `invoice_pdf` (default layout when omitted). */
  layout_id?: number
}

/** POST `.../emails` body: every field optional. */
export interface CreateOutboundEmailPayload {
  attach_pdf?: boolean
}
