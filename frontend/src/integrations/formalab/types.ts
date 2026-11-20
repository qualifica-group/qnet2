/** Mirrors the frozen data_contract of spec 0422. */

export type FormalabSyncTrigger = 'schedule' | 'manual' | 'webhook' | 'work_order'
export type FormalabSyncOutcome = 'running' | 'success' | 'failure'

export interface FormalabSyncRun {
  id: number
  trigger: FormalabSyncTrigger
  outcome: FormalabSyncOutcome
  started_at: string
  finished_at: string | null
  classes_synced: number
  work_orders_updated: number
  error_message: string | null
  /** Set on a run that succeeded with a caveat, e.g. course fields whose attribute code no longer exists. */
  warning_message: string | null
}

export interface FormalabStatus {
  base_url: string | null
  credentials_configured: boolean
  inbound_enabled: boolean
  sync_interval_minutes: number
  last_sync: FormalabSyncRun | null
  classes_count: number
  linked_work_orders_count: number
  /** Course field codes (flexible attributes) that no attribute carries any more: they stop updating. */
  missing_course_fields: string[]
  calls_last_24h: { total: number; failures: number; inbound: number; outbound: number }
}

export interface FormalabConnectionTest {
  ok: boolean
  latency_ms: number | null
  message: string | null
}

export interface FormalabLinkSummary {
  linked: number
  ambiguous: number
  unmatched: number
}

export interface FormalabClass {
  edition_id: number
  label: string
  code: string
  title: string
  status: string | null
  course_status: string | null
  start_date: string | null
  end_date: string | null
  total_hours: number | null
  exam_date: string | null
  site_name: string | null
  site_city: string | null
  learners_count: number | null
  last_seen_at: string | null
}

export interface FormalabLink {
  edition_id: number
  linked_at: string
  linked_by: { id: number; name: string } | null
  synced_at: string | null
  sync_error: string | null
  class: FormalabClass | null
}

export interface FormalabWorkOrderLink {
  eligible: boolean
  can_manage: boolean
  link: FormalabLink | null
}
