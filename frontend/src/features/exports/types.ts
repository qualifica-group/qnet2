/**
 * Generic per-table export types (spec 0014). The feature is parametrized on
 * a `domain` string (e.g. `companies`); every shape below matches the frozen
 * backend contract 1:1 (`ExportRunResource`) so no field is invented here.
 */

/** Lifecycle of an export run, mirrored from `App\Enums\ExportStatus`. */
export type ExportStatus = 'processing' | 'completed' | 'failed'

/** Output file format, mirrored from `App\Enums\ExportFormat`. */
export type ExportFormat = 'csv' | 'xlsx'

/** One exported column: `colId` selects the value, `header` labels the file. */
export interface ExportColumnInput {
  colId: string
  header: string
}

/** A single sort directive, mirroring the grid's column state sort. */
export interface ExportSortModelItem {
  colId: string
  sort: 'asc' | 'desc'
}

/**
 * Body sent to `POST /exports/{domain}` (frozen contract). `columns` must be
 * non-empty, in the grid's visible order; `sortModel`/`filterModel`/`search`
 * are optional and mirror the current grid state exactly.
 */
export interface CreateExportPayload {
  format: ExportFormat
  columns: ExportColumnInput[]
  sortModel?: ExportSortModelItem[]
  filterModel?: Record<string, unknown>
  search?: string
  /**
   * Row-set scope to one parent record (spec 0067 D-5, e.g. an Opportunity's
   * Quotes panel export). Frozen into `ExportRun.state` and reapplied by the
   * generation job. Omitted ⇒ today's unscoped export, unchanged.
   */
  opportunityId?: number | null
}

/** The export run resource returned by every endpoint (`ExportRunResource`). */
export interface ExportRun {
  id: number
  resource: string
  status: ExportStatus
  format: ExportFormat
  original_filename: string
  row_count: number | null
  /** Derived: `file_path !== null && status === 'completed'`. */
  has_file: boolean
  created_at: string
}

/** Response shape of `POST/GET /exports/{domain}[/{id}]` (envelope `data`). */
export interface ExportRunDetail {
  export_run: ExportRun
}

/**
 * Grid state captured for both the export payload and the dialog's summary
 * (N active filters, sort, search, M columns), built once from the live grid
 * API by `buildExportGridState`.
 */
export interface ExportGridState {
  columns: ExportColumnInput[]
  sortModel: ExportSortModelItem[]
  filterModel: Record<string, unknown>
  search: string
}
