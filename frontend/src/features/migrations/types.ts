/**
 * External data migrations types (spec 0013): a super-admin only, two-phase
 * flow (read-only preview -> queued import) against the frozen
 * `/api/migrations` `data_contract`. Every shape below matches that contract
 * 1:1 -- no field is invented here.
 */

/**
 * A registered migration source, as returned by `GET /migrations`: its
 * 1-based import `phase` (null when outside the recommended order), its
 * latest run of any status (null when it never ran) and when a run last
 * completed (null when it was never migrated).
 */
export interface MigrationSourceSummary {
  key: string
  label: string
  phase: number | null
  last_run: MigrationLastRun | null
  migrated_at: string | null
}

/** A source's latest run as listed by `GET /migrations` (report flag only). */
export type MigrationLastRun = Omit<MigrationRunCreated, 'status'> & { status: MigrationRunStatus }

/**
 * A source to import before the selected one. `required`: without it the
 * rows fail or are skipped; otherwise the link is left empty with a warning.
 */
export interface MigrationDependency {
  source: string
  required: boolean
}

/** A column exposed by a source's external records, driving the preview table. */
export interface MigrationColumn {
  id: string
  label: string
  type: 'string' | 'number' | 'boolean' | 'date'
}

/** A single external record row, keyed by column id. */
export type MigrationPreviewRow = Record<string, string | number | boolean | null>

/**
 * The safe-to-display HTTP request the external source is expected to serve
 * (no token/credentials -- backend strips those before returning this).
 */
export interface MigrationColumnsRequest {
  method: string
  base_url: string
  path: string
  url: string
}

/** Pagination metadata of the canonical sample response envelope. */
export interface MigrationColumnsSampleMeta {
  current_page: number
  per_page: number
  total: number
}

/** The canonical response envelope the external source must return. */
export interface MigrationColumnsSample {
  data: MigrationPreviewRow[]
  meta: MigrationColumnsSampleMeta
}

/** Response shape of `GET /migrations/{source}/columns` (envelope `data`). */
export interface MigrationColumnsTemplate {
  dependencies: MigrationDependency[]
  columns: MigrationColumn[]
  request: MigrationColumnsRequest
  sample: MigrationColumnsSample
}

export interface MigrationPreviewPagination {
  page: number
  per_page: number
  total: number | null
  has_more: boolean
}

/** Response shape of `GET /migrations/{source}/preview` (envelope `data`). */
export interface MigrationPreviewPage {
  rows: MigrationPreviewRow[]
  pagination: MigrationPreviewPagination
}

/** Lifecycle of a migration run, mirrored from `App\Enums\MigrationStatus`. */
export type MigrationRunStatus = 'pending' | 'processing' | 'completed' | 'failed'

/** The run resource as returned right after `POST /migrations/{source}/import` (201). */
export interface MigrationRunCreated {
  id: number
  source: string
  status: 'pending'
  total_rows: number
  created_rows: number
  skipped_rows: number
  failed_rows: number
  has_report: boolean
  created_at: string
}

/** A single warning/error entry accumulated per row during the import. */
export interface MigrationReportEntry {
  old_id: number | string | null
  level: 'warning' | 'error'
  message: string
}

/**
 * One entry of the saved mass-import plan (spec 0046): a source, its human
 * label, and whether the "Import all" run includes it. Order is the array order.
 */
export interface MigrationPlanItem {
  source: string
  label: string
  enabled: boolean
}

/** The saved mass-import plan as returned by `GET/PUT /migrations/plan`. */
export interface MigrationPlan {
  sources: MigrationPlanItem[]
}

/** The `PUT /migrations/plan` body item — no label (server derives it). */
export interface MigrationPlanInput {
  source: string
  enabled: boolean
}

/**
 * The "Import all" aggregate run (spec 0046): its status, the ordered snapshot
 * of the enabled sources it runs, and one child `MigrationRun` per source
 * actually started (in execution order). Returned by
 * `POST /migrations/mass-runs` (with `runs: []`) and its polling endpoint.
 */
export interface MassMigrationRun {
  id: number
  status: MigrationRunStatus
  sources: string[]
  created_at: string
  runs: MigrationRun[]
}

/** The run resource as returned by the polling endpoint `GET .../runs/{migrationRun}`. */
export interface MigrationRun {
  id: number
  source: string
  status: MigrationRunStatus
  total_rows: number
  created_rows: number
  skipped_rows: number
  failed_rows: number
  report: MigrationReportEntry[] | null
  created_at: string
}
