/**
 * Proforma requests types. Generic table types live in `features/table/types.ts`.
 * Source of truth: spec 0193 frozen `data_contract`.
 */

import type { ResourcePermissions } from '@/features/authorization/types'

export const PROFORMA_REQUEST_KINDS = ['consultancy', 'institution'] as const
export type ProformaRequestKind = (typeof PROFORMA_REQUEST_KINDS)[number]

export const PROFORMA_REQUEST_STATUSES = ['pending', 'issued'] as const
export type ProformaRequestStatus = (typeof PROFORMA_REQUEST_STATUSES)[number]

/** Per-work-order aggregate shown by the "€" cell: `none` when no request exists. */
export type ProformaStatus = 'none' | ProformaRequestStatus

/** `{id, name}` projection of a related record. */
export interface NamedRef {
  id: number
  name: string
}

/** `ProformaRequestResource` returned by GET/PATCH/POST (envelope `data`). */
export interface ProformaRequest {
  id: number
  kind: ProformaRequestKind
  status: ProformaRequestStatus
  issued_at: string | null
  note: string
  work_order: { id: number; code: string; title: string | null }
  company: NamedRef | null
  customer: NamedRef | null
  supplier: NamedRef | null
  payment_method: NamedRef | null
  assigned_to: NamedRef
  assigned_by: NamedRef
  created_at: string
  updated_at: string
}

export interface ProformaRequestWithPermissions extends ProformaRequest {
  permissions: ResourcePermissions
}

/** GET /work-orders/{id}/proforma-requests/summary (envelope `data`). */
export interface ProformaSummary {
  status: ProformaStatus
  last_requested_at: string | null
  payment_method: NamedRef | null
  work_order: { id: number; code: string }
}

/** Body shared by POST (create for a work order) and PATCH (edit): the note is the only writable field. */
export interface ProformaNotePayload {
  note: string
}
