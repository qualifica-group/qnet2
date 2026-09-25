/**
 * Time entries (segnatempo) module types — spec 0122. Source of truth: the
 * frozen `data_contract` (`TimeEntry`, `DaySummary`, `OverviewStats`,
 * `PulseStats`, `TeamPulse`). Shared by every later microtask (F2..F7): do
 * not redeclare a shape already here, import it.
 */

import type { Pagination } from '@/features/notifications/types'

/** `{id, name, color, icon}` projection of `task_types` (D-1 context: reused from Task). */
export interface TaskTypeRef {
  id: number
  name: string
  /** `BADGE_COLOR_TOKENS` token, never a hex. */
  color: string
  /** Curated lucide name, or null when unset. */
  icon: string | null
}

/** `{id, name}` projection shared by `user`, `registry` and `opportunity`. */
export interface TimeEntryNamedRef {
  id: number
  name: string
}

/** The linked work order ("Commessa") projection. */
export interface TimeEntryWorkOrderRef {
  id: number
  code: string
  title: string
}

/** The linked task projection: a task is identified by its `title`, not a `name`. */
export interface TimeEntryTaskRef {
  id: number
  title: string
}

/** The linked "Fase" (spec 0163 D-1/D-2) projection: a snapshot at write time, not live. */
export interface TimeEntryWorkOrderStageRef {
  id: number
  name: string
}

/** Per-instance authorization the actor holds on a given time entry. */
export interface TimeEntryPermissions {
  update: boolean
  delete: boolean
}

/** Single time entry as returned by every read/write endpoint (`TimeEntryResource`). */
export interface TimeEntry {
  id: number
  user: TimeEntryNamedRef
  /** `Y-m-d` */
  date: string
  title: string
  task_type: TaskTypeRef
  /** `HH:MM`, both present or both null (D-6). */
  start_time: string | null
  end_time: string | null
  minutes: number
  notes: string | null
  registry: TimeEntryNamedRef | null
  opportunity: TimeEntryNamedRef | null
  work_order: TimeEntryWorkOrderRef | null
  task: TimeEntryTaskRef | null
  /** Snapshot at write time (spec 0163 D-2): a later task/fase change never updates it. */
  work_order_stage: TimeEntryWorkOrderStageRef | null
  created_at: string
  updated_at: string
  permissions: TimeEntryPermissions
}

/** `App\Enums\TimeEntryDailyStatus` (D-7): the ONLY thing UI logic may branch on. */
export type DailyStatus = 'no_target' | 'under_target' | 'on_target' | 'over_target'

/** One day of the dashboard list, present for EVERY date of the period (D-11/list contract). */
export interface DaySummary {
  date: string
  /** ISO weekday, 1 (Monday) to 7 (Sunday). */
  weekday: number
  is_holiday: boolean
  is_non_working_day: boolean
  /** At least one time entry that day; a day note alone does not count (D-7). */
  is_active: boolean
  target_minutes: number
  total_minutes: number
  /** Two-decimal percentage, e.g. `93.75`. */
  utilization_percentage: number
  status: DailyStatus
  day_note: string | null
  /** Ordered `start_time` asc (null last), then `id` asc. */
  entries: TimeEntry[]
}

/** Per-request authorization/context metadata alongside the day list (D-8/D-10). */
export interface TimeEntriesListMeta {
  selected_user: TimeEntryNamedRef
  daily_target_minutes: number
  can_write: boolean
  can_filter_users: boolean
  can_export: boolean
  can_export_monthly: boolean
  team: {
    can_view: boolean
    is_full_list: boolean
    members_count: number
  }
}

/** Body of `GET /api/time-entries`. */
export interface TimeEntriesListResponse {
  items: DaySummary[]
  export_link: string | null
  pagination: Pagination
  meta: TimeEntriesListMeta
}

/** `data` of `GET /api/time-entries/stats/overview` (formulas: D-11). */
export interface OverviewStats {
  period: { date_from: string; date_to: string }
  daily_target_minutes: number
  period_target_minutes: number
  working_days: number
  tracked_minutes: number
  tracked_days: number
  average_daily_focus_minutes: number
  anomalies: {
    over_target_days: number
    under_target_days: number
  }
}

/** Coverage of tracked minutes against the period target (D-11). */
export interface Coverage {
  percentage: number
  working_days: number
  tracked_days: number
}

/** Minutes tracked for one task type within the period, descending by minutes (D-11). */
export interface Cluster {
  task_type: TaskTypeRef
  minutes: number
  percentage: number
}

/** `data` of `GET /api/time-entries/stats/pulse`. */
export interface PulseStats {
  coverage: Coverage
  primary_cluster: Cluster | null
  other_clusters: Cluster[]
}

/** One row of the team stats list (D-10). */
export interface TeamPulseMember {
  user: {
    id: number
    name: string
    email: string
    avatar_url: string | null
  }
  /** Ascending, `[]` if none (0166 D-7/AC-012): the member can have several managers. */
  manager_ids: number[]
  job_description: string | null
  roles: string[]
  business_functions: Array<{ id: number; name: string; is_manager: boolean }>
  operational_site: { id: number; label: string | null } | null
  coverage: Coverage
  primary_cluster: Cluster | null
}

/** `data` of `GET /api/time-entries/stats/team`, ordered by name. */
export interface TeamPulse {
  is_full_list: boolean
  items: TeamPulseMember[]
}

/** `data` of `GET /api/tasks/{task}/time-entries` (D-9). */
export interface TaskTimeEntriesResponse {
  total_minutes: number
  can_create: boolean
  /** Ordered `date` desc, `start_time` desc (null last), `id` desc. */
  items: TimeEntry[]
}

/** Filters shared by the list, overview, pulse and filtered-export endpoints (F). */
export interface TimeEntriesFilterParams {
  user_id?: number
  period_preset?: 'day' | 'week' | 'month' | 'year'
  /** `Y-m-d` */
  date_from?: string
  date_to?: string
  task_type_ids?: number[]
  registry_ids?: number[]
  opportunity_ids?: number[]
  work_order_ids?: number[]
  task_ids?: number[]
  daily_statuses?: DailyStatus[]
  is_active?: boolean
}

/** Backend-allowed sort columns for `GET /api/time-entries` (allow-list, no `orderByRaw`). */
export type TimeEntriesSortBy = 'date' | 'target_minutes' | 'is_active'

/** Full request of `GET /api/time-entries`. */
export interface TimeEntriesListParams extends TimeEntriesFilterParams {
  sort_by?: TimeEntriesSortBy
  sort_direction?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

/** Request of `GET /api/time-entries/stats/team` (no entity filters, only the period). */
export interface TeamStatsParams {
  period_preset?: 'day' | 'week' | 'month' | 'year'
  date_from?: string
  date_to?: string
}

/** Body of `PUT /api/time-entries/day-notes`. */
export interface TimeEntryDayNotePayload {
  user_id?: number
  date: string
  note: string | null
}

/** `data` of `PUT /api/time-entries/day-notes` (empty-after-trim note = deleted). */
export interface TimeEntryDayNoteResponse {
  date: string
  note: string | null
}

/**
 * Body of `POST /api/time-entries`. With `task_id` set, the server IMPOSES
 * `title`/`registry_id`/`opportunity_id`/`work_order_id` from the task and
 * ignores whatever the client sent for them (D-5) — they stay valid keys here
 * only because the standalone form still lets the user pre-fill them before
 * picking a task.
 */
export interface CreateTimeEntryPayload {
  /** Only accepted with `time-entries.manageAll`; defaults server-side to the actor. */
  user_id?: number
  date: string
  /** `required_without:task_id`. */
  title?: string
  task_type_id: number
  start_time?: string
  end_time?: string
  minutes: number
  notes?: string | null
  registry_id?: number | null
  opportunity_id?: number | null
  work_order_id?: number | null
  task_id?: number | null
  /** `prohibited` without `work_order_id`; ignored server-side once `task_id` is set (spec 0163 D-1). */
  work_order_stage_id?: number | null
}

/** Body of `PUT /api/time-entries/{id}` — same as create, `user_id` prohibited. */
export type UpdateTimeEntryPayload = Omit<CreateTimeEntryPayload, 'user_id'>

/** Body of `POST /api/tasks/{task}/time-entries` (D-9): no title/links, both come from the task. */
export interface CreateTaskTimeEntryPayload {
  date: string
  task_type_id: number
  start_time?: string
  end_time?: string
  minutes: number
  notes?: string | null
}

/** Query of `GET /api/time-entries/exports/filtered` (F, single `user_id`). */
export type FilteredTimeEntriesExportParams = TimeEntriesFilterParams

/** Query of `GET /api/time-entries/exports/monthly`. */
export interface MonthlyTimeEntriesExportParams {
  month: number
  year: number
  /** Omitted = every user with a time entry that month. */
  user_ids?: number[]
}
