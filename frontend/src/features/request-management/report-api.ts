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

/**
 * One selectable GA2 Operatore (spec 0109): `key` is a user id as a string,
 * or the literal `unassigned` — "Non assegnato" IS a GA2 row (spec 0106
 * D-13), so it is selectable like any other. `label` comes from the server
 * (a user's name, or the report's own "Non assegnato" catalogue entry) and is
 * rendered as-is — NEVER through i18next.
 */
export interface RequestReportOperator {
  key: string
  label: string
}

/**
 * One selectable operational site (spec 0112): `key` is a site id as a
 * string, the same shape as `operators[].key` so both groups share
 * `RequestReportKeyGroup` without an adapter. `label` is the site's composed
 * address (D-8) and is rendered as-is — NEVER through i18next. Sites without
 * an address keep an empty label and stay selectable (D-8): hiding a real
 * site would make it unfilterable.
 */
export interface RequestReportSite {
  key: string
  label: string
}

/** Which rows a branch emits (rev-2 D-13). */
export type RequestReportRowMode = 'total_only' | 'operators_only' | 'all'

/**
 * The four filter dimensions, as they travel on the wire — shared by the CSV
 * report and the dashboard, which is the point (spec 0107 D-4).
 */
export interface RequestReportFilterPayload {
  /** `YYYY-MM-DD`, inclusive lower bound. */
  date_from: string
  /** `YYYY-MM-DD`, inclusive upper bound. */
  date_to: string
  /** Branch keys to include, min 1, each in the server's config allow-list. */
  category_keys: string[]
  row_mode: RequestReportRowMode
  /**
   * GA2 keys to narrow the CALCULATION to (spec 0109, D-1). OMITTED means
   * every operator — including ones hired after this selection was made
   * (D-2) — so it is left out whenever all are selected, and under
   * `total_only`, where there are no operator rows to narrow (D-4).
   */
  operator_keys?: string[]
  /**
   * Operational site keys to narrow the CALCULATION to (spec 0112, D-1): a
   * request belongs to the site(s) of its GA2 Operatore. OMITTED means every
   * site — including one created after this selection was made (D-4) — so it
   * follows `operator_keys` exactly: dropped when all are selected, when the
   * picker has nothing to offer, and under `total_only` (D-7).
   */
  site_keys?: string[]
}

export interface CreateRequestReportPayload extends RequestReportFilterPayload {
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

/**
 * Loads the GA2 the actor may filter by (spec 0109, D-6): all-time and
 * independent of both the dates and the branches picked, exactly like the
 * branch list above. `GET /request-management/report/operators`.
 */
export async function fetchRequestManagementReportOperators(): Promise<RequestReportOperator[]> {
  const { data } = await apiClient.get<ApiResponse<{ operators: RequestReportOperator[] }>>(
    '/request-management/report/operators',
  )
  return data.data.operators
}

/**
 * Loads the operational sites the actor may filter by (spec 0112, D-10):
 * all-time and independent of the dates, branches and operators picked,
 * exactly like the two lists above. `GET /request-management/report/sites`.
 */
export async function fetchRequestManagementReportSites(): Promise<RequestReportSite[]> {
  const { data } = await apiClient.get<ApiResponse<{ sites: RequestReportSite[] }>>(
    '/request-management/report/sites',
  )
  return data.data.sites
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
