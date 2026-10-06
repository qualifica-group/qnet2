/**
 * Email Templates types (spec 0175 frozen `data_contract`). FE-02 (wave 2)
 * adds the CRUD write payloads and the form mode below the shared shapes
 * FE-01 seeded.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

/** The only admitted value today (D-10); kept as an array so the module Select/schema extend without a shape change (mirrors `DOCUMENT_LAYOUT_MODULES`). */
export const EMAIL_TEMPLATE_MODULES = ['work_orders', 'invoices'] as const
export type EmailTemplateModule = (typeof EMAIL_TEMPLATE_MODULES)[number]

/**
 * Single email template as returned by GET/POST/PUT/PATCH
 * `/api/email-templates[/{id}]` (envelope `data`).
 */
export interface EmailTemplate {
  id: number
  name: string
  module: EmailTemplateModule
  subject: string
  /** HTML sanitized with `RichTextSanitizer` (D-11): no `<img>`, no mentions. */
  body: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

/**
 * An `EmailTemplate` carrying the actor's authorization metadata (spec 0004),
 * as returned by `GET /email-templates/{id}` (`okWithPermissions`).
 */
export interface EmailTemplateWithPermissions extends EmailTemplate {
  permissions: ResourcePermissions
}

/** Payload for POST /email-templates (create). */
export interface CreateEmailTemplatePayload {
  name: string
  module: EmailTemplateModule
  subject: string
  body: string
  description?: string | null
  is_active?: boolean
}

/**
 * Payload for PUT|PATCH /email-templates/{id} (update). `module` is NEVER a
 * key here (data_contract: "module immutabile in update", the backend
 * `prohibited`-validates its mere presence).
 */
export type UpdateEmailTemplatePayload = Partial<Omit<CreateEmailTemplatePayload, 'module'>>

/**
 * Discriminated form mode shared by the form hook/meta-resolver and the
 * `EmailTemplateForm` component (mirrors `TaskImportanceFormMode`).
 */
export type EmailTemplateFormMode =
  | { type: 'create' }
  | { type: 'edit'; emailTemplate: EmailTemplateWithPermissions }
