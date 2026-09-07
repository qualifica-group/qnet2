/**
 * Aggregated activity log types (spec 0034). Mirrors the frozen contract of
 * `GET /api/activity-log/{resource}/{id}` — the endpoint is generic across
 * modules, so this feature carries no per-module knowledge beyond `module`
 * being a free-form alias resolved to a label by the caller (i18n).
 */

/** Spatie activitylog event names, as emitted by `LogsModelActivity`. */
export type ActivityLogEvent = 'created' | 'updated' | 'deleted' | 'restored'

/**
 * Timeline narrowing sent as the `event` query param; `'all'` is the absence
 * of the param, not a value the backend accepts.
 */
export type ActivityLogEventFilter = 'all' | 'created' | 'updated'

/** Actor who triggered the logged event, resolved server-side. */
export interface ActivityLogCauser {
  id: number | null
  name: string | null
}

/** A single dirty field captured on the logged event. */
export interface ActivityLogChange {
  field: string
  old_value: unknown
  new_value: unknown
  /** Human-readable label for `old_value`, resolved server-side; set only when `field` is an FK. */
  old_display: string | null
  /** Human-readable label for `new_value`, resolved server-side; set only when `field` is an FK. */
  new_display: string | null
}

/** One aggregated activity-log entry (root record or a declared relation). */
export interface ActivityLogEntry {
  id: number
  logged_at: string
  event: ActivityLogEvent
  /** Morph alias of the logged subject (e.g. 'user', 'personal_data'). */
  module: string
  subject_id: number
  causer: ActivityLogCauser
  changes: ActivityLogChange[]
}

/** One keyset page as returned in the envelope `data`. */
export interface ActivityLogPage {
  items: ActivityLogEntry[]
  next_cursor: string | null
}
