import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { ExportFormat } from '@/features/exports/types'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'

/**
 * The report's own async cycle (spec 0106 D-8), a module-owned sibling of the
 * generic `features/exports` types: the contract shape differs
 * (`file_name`/no `format`/no `row_count`), so it is not the same `ExportRun`.
 * The `ExportFormat` union IS shared with them — the backend allow-lists both
 * against the same `config('exports.formats')`.
 */
export type RequestReportStatus = 'processing' | 'completed' | 'failed'

/** Matches `ExportRunResource` as exposed by the three `/request-management/report` routes. */
export interface RequestReportRun {
  id: number
  status: RequestReportStatus
  file_name: string
  created_at: string
}

/** One selectable branch of the report (rev-2 D-11): a config key, never a raw `product_categories` node. */
export interface RequestReportCategory {
  key: string
  /** Domain value (e.g. "GOL"), rendered as-is — NOT run through i18next (rev-2 D-14). */
  label: string
}

/** Which rows a branch emits (rev-2 D-13). */
export type RequestReportRowMode = 'total_only' | 'operators_only' | 'all'

export interface CreateRequestReportPayload {
  /** `YYYY-MM-DD`, inclusive lower bound. */
  date_from: string
  /** `YYYY-MM-DD`, inclusive upper bound. */
  date_to: string
  /** Branch keys to include, min 1, each in the server's config allow-list. */
  category_keys: string[]
  row_mode: RequestReportRowMode
  /** File the run produces (user directive 2026-09-08): `csv` or `xlsx`. */
  format: ExportFormat
}

/** Creates the run and dispatches the backend job (`POST /request-management/report`). */
export async function createRequestManagementReport(
  payload: CreateRequestReportPayload,
): Promise<RequestReportRun> {
  const { data } = await apiClient.post<ApiResponse<{ export_run: RequestReportRun }>>(
    '/request-management/report',
    payload,
  )
  return data.data.export_run
}

/**
 * Loads the branches the actor may include (rev-2 D-11/D-12): only those
 * with at least one in-scope request, all-time (independent of the date
 * range picked in the dialog). `GET /request-management/report/categories`,
 * NOT the tab strip's `/request-management/product-categories` — that
 * returns real category nodes, not the report's six config branches.
 */
export async function fetchRequestManagementReportCategories(): Promise<RequestReportCategory[]> {
  const { data } = await apiClient.get<ApiResponse<{ categories: RequestReportCategory[] }>>(
    '/request-management/report/categories',
  )
  return data.data.categories
}

/** Polls the current state of a report run (`GET /request-management/report/{id}`). */
export async function getRequestManagementReport(reportRunId: number): Promise<RequestReportRun> {
  const { data } = await apiClient.get<ApiResponse<{ export_run: RequestReportRun }>>(
    `/request-management/report/${reportRunId}`,
  )
  return data.data.export_run
}

/**
 * Downloads the generated CSV of a completed run
 * (`GET /request-management/report/{id}/download`).
 */
export async function downloadRequestManagementReport(reportRunId: number): Promise<void> {
  const response = await apiClient.get<Blob>(`/request-management/report/${reportRunId}/download`, {
    responseType: 'blob',
  })
  const filename =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    `request-management-report-${reportRunId}.csv`
  saveBlob(response.data, filename)
}
