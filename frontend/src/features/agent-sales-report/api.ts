import axios from 'axios'
import { apiClient } from '@/api/client'
import { filenameFromContentDisposition, saveBlob } from '@/lib/download'
import type { FilterRules } from '@/features/table/types'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { ExportSortModelItem } from '@/features/exports/types'

export const AGENT_SALES_REPORT_DOMAIN = 'agent-sales-report'
export const AGENT_SALES_REPORT_PERMISSION = 'agent-sales-report.view'

/** The two synchronous workbook exports (spec 0365 D-9). */
export type AgentSalesReportExportKind = 'details' | 'lines'

/** Grid state sent to the export endpoints (the `columns` key is optional and ignored by the server). */
export interface AgentSalesReportExportPayload {
  sortModel: ExportSortModelItem[]
  filterModel: Record<string, unknown>
  search: string | null
  advancedFilters?: AdvancedFilterValues
  customFilterRules?: FilterRules | null
}

const FALLBACK_FILENAME: Record<AgentSalesReportExportKind, string> = {
  details: 'report-vendite-dettagli.xlsx',
  lines: 'report-vendite-righe.xlsx',
}

/** Downloads the workbook for the given grid state (`POST /agent-sales-report/exports/{kind}`). */
export async function downloadAgentSalesReportExport(
  kind: AgentSalesReportExportKind,
  payload: AgentSalesReportExportPayload,
): Promise<void> {
  const response = await apiClient.post<Blob>(`/${AGENT_SALES_REPORT_DOMAIN}/exports/${kind}`, payload, {
    responseType: 'blob',
  })
  const filename =
    filenameFromContentDisposition(response.headers['content-disposition']) ?? FALLBACK_FILENAME[kind]
  saveBlob(response.data, filename)
}

/**
 * With `responseType: 'blob'` an error body arrives as a Blob too: read the
 * envelope `message` back out of it (null when absent or not JSON).
 */
export async function readErrorMessage(error: unknown): Promise<string | null> {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const body: unknown = error.response?.data
  try {
    const raw = body instanceof Blob ? await body.text() : body
    const parsed: unknown = typeof raw === 'string' ? JSON.parse(raw) : raw
    const message = (parsed as { message?: unknown } | null)?.message
    return typeof message === 'string' && message !== '' ? message : null
  } catch {
    return null
  }
}
