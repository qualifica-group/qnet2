import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  MassMigrationRun,
  MigrationColumnsTemplate,
  MigrationPlan,
  MigrationPlanInput,
  MigrationPreviewPage,
  MigrationRun,
  MigrationRunCreated,
  MigrationSourceSummary,
  MigrationStartOptions,
} from '@/features/migrations/types'

/** Fetches the registered migration sources (`GET /migrations`). */
export async function fetchMigrationSources(): Promise<MigrationSourceSummary[]> {
  const { data } = await apiClient.get<ApiResponse<{ sources: MigrationSourceSummary[] }>>(
    '/migrations',
  )
  return data.data.sources
}

/**
 * Fetches the expected template for a source (`GET /migrations/{source}/columns`):
 * the field schema, the safe-to-display request the external source is
 * expected to serve, and the canonical sample response envelope.
 */
export async function fetchMigrationColumns(source: string): Promise<MigrationColumnsTemplate> {
  const { data } = await apiClient.get<ApiResponse<MigrationColumnsTemplate>>(
    `/migrations/${source}/columns`,
  )
  return data.data
}

/**
 * Fetches one page of a source's read-only external preview (fase 1)
 * (`GET /migrations/{source}/preview`).
 */
export async function fetchMigrationPreview(
  source: string,
  page: number,
  perPage: number,
): Promise<MigrationPreviewPage> {
  const { data } = await apiClient.get<ApiResponse<MigrationPreviewPage>>(
    `/migrations/${source}/preview`,
    { params: { page, per_page: perPage } },
  )
  return data.data
}

/**
 * Starts the queued import (fase 2) for a source
 * (`POST /migrations/{source}/import`); a date limits it to records created or
 * modified since then (spec 0345), null imports everything; `updateExisting`
 * also realigns the records already imported (spec 0372).
 */
export async function startMigrationImport(
  source: string,
  { updatedSince, updateExisting }: MigrationStartOptions,
): Promise<MigrationRunCreated> {
  const { data } = await apiClient.post<ApiResponse<{ migration_run: MigrationRunCreated }>>(
    `/migrations/${source}/import`,
    { updated_since: updatedSince, update_existing: updateExisting },
  )
  return data.data.migration_run
}

/**
 * Polls the current state of a run
 * (`GET /migrations/{source}/runs/{migrationRun}`).
 */
export async function fetchMigrationRun(source: string, runId: number): Promise<MigrationRun> {
  const { data } = await apiClient.get<ApiResponse<{ migration_run: MigrationRun }>>(
    `/migrations/${source}/runs/${runId}`,
  )
  return data.data.migration_run
}

/** Fetches the saved mass-import plan (`GET /migrations/plan`). */
export async function fetchMigrationPlan(): Promise<MigrationPlan> {
  const { data } = await apiClient.get<ApiResponse<{ plan: MigrationPlan }>>('/migrations/plan')
  return data.data.plan
}

/** Upserts the mass-import plan (`PUT /migrations/plan`), returning it reconciled. */
export async function saveMigrationPlan(sources: MigrationPlanInput[]): Promise<MigrationPlan> {
  const { data } = await apiClient.put<ApiResponse<{ plan: MigrationPlan }>>('/migrations/plan', {
    sources,
  })
  return data.data.plan
}

/** Starts the mass import from the saved plan (`POST /migrations/mass-runs`). */
export async function startMassMigration({
  updatedSince,
  updateExisting,
}: MigrationStartOptions): Promise<MassMigrationRun> {
  const { data } = await apiClient.post<ApiResponse<{ mass_migration_run: MassMigrationRun }>>(
    '/migrations/mass-runs',
    { updated_since: updatedSince, update_existing: updateExisting },
  )
  return data.data.mass_migration_run
}

/** Polls a mass-import run (`GET /migrations/mass-runs/{massMigrationRun}`). */
export async function fetchMassMigrationRun(runId: number): Promise<MassMigrationRun> {
  const { data } = await apiClient.get<ApiResponse<{ mass_migration_run: MassMigrationRun }>>(
    `/migrations/mass-runs/${runId}`,
  )
  return data.data.mass_migration_run
}
