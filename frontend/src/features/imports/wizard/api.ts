import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { GeoValue } from '@/features/geo/geo-select'
import type {
  BulkAssignImportRowPayload,
  BulkAssignImportRowResult,
  ConfigureImportPayload,
  ConfirmImportPayload,
  ImportMappingTemplate,
  ImportRowResolution,
  ImportRunDetail,
  ImportRunRowsPage,
  ImportRunRowsQuery,
  ImportRunRowUpdateResult,
  ImportRunSummary,
  ImportRunSummaryReport,
} from '@/features/imports/wizard/types'

/**
 * Body of `PATCH .../rows/{row}`: at least one block is present. `geo`, when
 * sent, is authoritative for the 4 geo levels (spec 0038) — the backend skips
 * its own fuzzy re-matching for them. `operator_id` sets/clears the row's
 * per-row operator override (`null` reverts to the run's global default).
 * `operational_site_id` sets/clears the row's per-row operational-site
 * override (`null` clears it — there is no global default to revert to).
 * `product_ids` sets/clears the row's per-row products-of-interest override
 * (spec 0094 D-4): `null` reverts to the run's global `product_ids` default,
 * `[]` is an explicit "no products on this row" override.
 */
export interface UpdateImportRunRowPayload {
  values?: Record<string, string>
  geo?: GeoValue
  operator_id?: number | null
  operational_site_id?: number | null
  product_ids?: number[] | null
}

/**
 * Uploads a file to start a wizard import run (`POST /imports/{domain}`).
 * Multipart body: axios infers the `multipart/form-data` boundary from the
 * `FormData` instance, so no `Content-Type` is set here (never force one).
 */
export async function analyzeImport(domain: string, file: File): Promise<ImportRunSummary> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}`,
    formData,
  )
  return data.data.import_run
}

/**
 * Polls/loads the full wizard state of a run (`GET /imports/{domain}/{id}`):
 * status, detected columns, mapping, global config and the mappable field
 * catalog. The single source of truth the wizard resumes from on reload.
 */
export async function getImportWizardRun(domain: string, importRunId: number): Promise<ImportRunDetail> {
  const { data } = await apiClient.get<ApiResponse<{ import_run: ImportRunDetail }>>(
    `/imports/${domain}/${importRunId}`,
  )
  return data.data.import_run
}

/**
 * Persists the column mapping, global config and dedup strategy, moving the
 * run from `configuring` to `staging` (`PUT .../configure`). Valid only
 * while `status === 'configuring'`.
 */
export async function configureImportRun(
  domain: string,
  importRunId: number,
  payload: ConfigureImportPayload,
): Promise<ImportRunSummary> {
  const { data } = await apiClient.put<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}/${importRunId}/configure`,
    payload,
  )
  return data.data.import_run
}

/**
 * Confirms a reviewed run, moving it to background processing
 * (`POST .../confirm`). Valid only while `status === 'reviewing'`. `payload`
 * opts the run into auto-converting its creatable rows into Opportunities;
 * omitted (or `convert_to_opportunity: false`) leaves the legacy behavior.
 */
export async function confirmImportRun(
  domain: string,
  importRunId: number,
  payload?: ConfirmImportPayload,
): Promise<ImportRunSummary> {
  const { data } = await apiClient.post<ApiResponse<{ import_run: ImportRunSummary }>>(
    `/imports/${domain}/${importRunId}/confirm`,
    payload,
  )
  return data.data.import_run
}

/**
 * SSRM datasource of the staged-rows review grid (`POST .../rows`). Owned by
 * F2 (review step); exposed here so the request/response shape is defined
 * exactly once against the frozen contract.
 */
export async function getImportRunRows(
  domain: string,
  importRunId: number,
  query: ImportRunRowsQuery,
): Promise<ImportRunRowsPage> {
  // FLAT paginated envelope ({ items, pagination }) via paginatedResponse, like
  // the tables SSRM datasource — not the { data } wrapper. Read body directly.
  const { data } = await apiClient.post<ImportRunRowsPage>(
    `/imports/${domain}/${importRunId}/rows`,
    query,
  )
  return data
}

/**
 * Inline edit of a single staged row (`PATCH .../rows/{row}`), re-validated
 * server-side. Owned by F2 (review step). `payload.geo` (spec 0038) replaces
 * the 4 geo levels in one shot instead of a text `values` edit.
 */
export async function updateImportRunRow(
  domain: string,
  importRunId: number,
  rowId: number,
  payload: UpdateImportRunRowPayload,
): Promise<ImportRunRowUpdateResult> {
  const { data } = await apiClient.patch<ApiResponse<ImportRunRowUpdateResult>>(
    `/imports/${domain}/${importRunId}/rows/${rowId}`,
    payload,
  )
  return data.data
}

/**
 * Bulk-assigns an operator and/or an operational site, or products of
 * interest (spec 0094 bulk delta, `product_ids`), to a selection of staged
 * rows (`PATCH .../rows/assign`, distinct from the single-row
 * `.../rows/{row}`). `payload` mirrors AG Grid's own server-side selection
 * state 1:1 — see `BulkAssignImportRowPayload`.
 */
export async function bulkAssignImportRow(
  domain: string,
  importRunId: number,
  payload: BulkAssignImportRowPayload,
): Promise<BulkAssignImportRowResult> {
  const { data } = await apiClient.patch<ApiResponse<BulkAssignImportRowResult>>(
    `/imports/${domain}/${importRunId}/rows/assign`,
    payload,
  )
  return data.data
}

/**
 * Resolves a `duplicate` staged row (`PATCH .../rows/{row}/resolution`,
 * spec 0036): skip it, create a new anagrafica+lead anyway, or update the
 * matched anagrafica's lead. Valid only for a `duplicate` row of a `reviewing`
 * run — same envelope/response shape as `updateImportRunRow`.
 */
export async function resolveImportRunRow(
  domain: string,
  importRunId: number,
  rowId: number,
  resolution: ImportRowResolution,
): Promise<ImportRunRowUpdateResult> {
  const { data } = await apiClient.patch<ApiResponse<ImportRunRowUpdateResult>>(
    `/imports/${domain}/${importRunId}/rows/${rowId}/resolution`,
    { resolution },
  )
  return data.data
}

/**
 * Pre-confirm summary of a reviewing run (`GET .../summary`). Owned by F3
 * (summary step).
 */
export async function getImportRunSummary(
  domain: string,
  importRunId: number,
): Promise<ImportRunSummaryReport> {
  const { data } = await apiClient.get<ApiResponse<{ summary: ImportRunSummaryReport }>>(
    `/imports/${domain}/${importRunId}/summary`,
  )
  return data.data.summary
}

/**
 * Lists every saved mapping template for a domain (spec 0035, team-shared —
 * not scoped to the actor), ordered id desc by the backend.
 */
export async function listMappingTemplates(domain: string): Promise<ImportMappingTemplate[]> {
  const { data } = await apiClient.get<ApiResponse<{ mapping_templates: ImportMappingTemplate[] }>>(
    `/imports/${domain}/mapping-templates`,
  )
  return data.data.mapping_templates
}

/**
 * Saves the current mapping/dedup strategy of an already-configured run as a
 * reusable template. The server snapshots `columns`/`column_mapping`/
 * `dedup_strategy` from the run itself — this call never sends them.
 */
export async function createMappingTemplate(
  domain: string,
  payload: { name: string; import_run_id: number },
): Promise<ImportMappingTemplate> {
  const { data } = await apiClient.post<ApiResponse<{ mapping_template: ImportMappingTemplate }>>(
    `/imports/${domain}/mapping-templates`,
    payload,
  )
  return data.data.mapping_template
}

/** Deletes a saved mapping template (owner-only server-side, spec 0035). */
export async function deleteMappingTemplate(domain: string, mappingTemplateId: number): Promise<void> {
  await apiClient.delete(`/imports/${domain}/mapping-templates/${mappingTemplateId}`)
}
