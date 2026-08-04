/**
 * Field-change-requests types (spec 0078). Mirrors `FieldChangeRequestResource`
 * (frozen `data_contract`) 1:1. The frontend stays generic on purpose (the
 * spec's primary architectural constraint, AC-054): it never hardcodes
 * `source_id`/`request-management`, only the `(resource, field)` indirection
 * the backend's `config/field-change-requests.php` drives.
 */

export const FIELD_CHANGE_REQUESTS_DOMAIN = 'field-change-requests'

export const FIELD_CHANGE_REQUEST_STATUSES = ['pending', 'approved', 'rejected'] as const
export type FieldChangeRequestStatus = (typeof FIELD_CHANGE_REQUEST_STATUSES)[number]

/** Minimal actor reference embedded in a request (requester / handler). */
export interface FieldChangeRequestActor {
  id: number
  name: string
}

/** Per-instance capabilities of the current actor on a given request. */
export interface FieldChangeRequestCan {
  approve: boolean
  reject: boolean
}

/** A single field change request, as returned by every endpoint's `data`. */
export interface FieldChangeRequestResource {
  id: number
  resource: string
  /** i18n key of the owning module, e.g. `navigation.requestManagement`. */
  resource_label: string
  subject_id: number
  subject_label: string
  /** Deep-link of the record, or `null` when it cannot be resolved. */
  subject_path: string | null
  field: string
  /** i18n key of the field, e.g. `requestManagement.columns.source`. */
  field_label: string
  current_value: unknown
  current_label: string | null
  requested_value: unknown
  requested_label: string | null
  reason: string | null
  status: FieldChangeRequestStatus
  requested_by: FieldChangeRequestActor
  requested_at: string
  handled_by: FieldChangeRequestActor | null
  handled_at: string | null
  handling_note: string | null
  can: FieldChangeRequestCan
}

/**
 * Payload for `POST /field-change-requests`. The server always computes
 * `current_value`/labels/status/requester (D-6): the client only ever sends
 * these four keys.
 */
export interface CreateFieldChangeRequestPayload {
  resource: string
  subject_id: number
  field: string
  requested_value: unknown
  reason?: string | null
}

/** Payload shared by approve/reject: an optional free-text note. */
export interface HandleFieldChangeRequestPayload {
  note?: string | null
}

/**
 * Parameters a write-interception point (panel field, grid cell — microtasks
 * E4/E5) hands to `requestFieldChange` to open the generic proposal dialog.
 * Deliberately free of any Fonte/Gestione-Richieste knowledge.
 */
export interface RequestFieldChangeParams {
  resource: string
  subjectId: number
  field: string
  requestedValue: unknown
  currentLabel?: string | null
  requestedLabel?: string | null
  /** i18n key of the field's human label, e.g. `requestManagement.columns.source`. */
  fieldLabelKey?: string
}
