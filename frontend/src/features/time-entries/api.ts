/**
 * Raw HTTP adapters for the time entries module (spec 0122 data_contract).
 * One function per endpoint; F2..F7 wrap these in TanStack Query hooks keyed
 * by `query-keys.ts`. Array filters (`task_type_ids`, `registry_ids`, …) are
 * sent as repeated `key[]=` params (Laravel convention), same as
 * `features/for-select/api.ts`.
 */

import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition } from '@/lib/download'
import type {
  CreateTaskTimeEntryPayload,
  CreateTimeEntryPayload,
  FilteredTimeEntriesExportParams,
  MonthlyTimeEntriesExportParams,
  OverviewStats,
  PulseStats,
  TaskTimeEntriesResponse,
  TeamPulse,
  TeamStatsParams,
  TimeEntriesFilterParams,
  TimeEntriesListParams,
  TimeEntriesListResponse,
  TimeEntry,
  TimeEntryDayNotePayload,
  TimeEntryDayNoteResponse,
  UpdateTimeEntryPayload,
} from '@/features/time-entries/types'

/** Axios config shared by every request carrying `key[]=`-style array filters. */
const ARRAY_PARAMS_CONFIG = { paramsSerializer: { indexes: true } } as const

/** Fetches one page of the day-grouped dashboard list (`GET /api/time-entries`). */
export async function fetchTimeEntries(
  params: TimeEntriesListParams = {},
): Promise<TimeEntriesListResponse> {
  const { data } = await apiClient.get<TimeEntriesListResponse>('/time-entries', {
    params,
    ...ARRAY_PARAMS_CONFIG,
  })
  return data
}

/** Fetches the period overview tiles (`GET /api/time-entries/stats/overview`, D-11). */
export async function fetchTimeEntriesOverview(
  params: TimeEntriesFilterParams = {},
): Promise<OverviewStats> {
  const { data } = await apiClient.get<ApiResponse<OverviewStats>>('/time-entries/stats/overview', {
    params,
    ...ARRAY_PARAMS_CONFIG,
  })
  return data.data
}

/** Fetches the operational pulse (coverage + clusters) (`GET /api/time-entries/stats/pulse`). */
export async function fetchTimeEntriesPulse(
  params: TimeEntriesFilterParams = {},
): Promise<PulseStats> {
  const { data } = await apiClient.get<ApiResponse<PulseStats>>('/time-entries/stats/pulse', {
    params,
    ...ARRAY_PARAMS_CONFIG,
  })
  return data.data
}

/** Fetches the team view rows (`GET /api/time-entries/stats/team`, D-10). */
export async function fetchTimeEntriesTeam(params: TeamStatsParams = {}): Promise<TeamPulse> {
  const { data } = await apiClient.get<ApiResponse<TeamPulse>>('/time-entries/stats/team', {
    params,
  })
  return data.data
}

/** Fetches a single time entry (`GET /api/time-entries/{id}`). */
export async function fetchTimeEntry(id: number): Promise<TimeEntry> {
  const { data } = await apiClient.get<ApiResponse<TimeEntry>>(`/time-entries/${id}`)
  return data.data
}

/** Creates a time entry (`POST /api/time-entries`). */
export async function createTimeEntry(payload: CreateTimeEntryPayload): Promise<TimeEntry> {
  const { data } = await apiClient.post<ApiResponse<TimeEntry>>('/time-entries', payload)
  return data.data
}

/** Updates a time entry (`PUT /api/time-entries/{id}`). `user_id` is prohibited server-side. */
export async function updateTimeEntry(
  id: number,
  payload: UpdateTimeEntryPayload,
): Promise<TimeEntry> {
  const { data } = await apiClient.put<ApiResponse<TimeEntry>>(`/time-entries/${id}`, payload)
  return data.data
}

/** Deletes a time entry (`DELETE /api/time-entries/{id}`). */
export async function deleteTimeEntry(id: number): Promise<void> {
  await apiClient.delete(`/time-entries/${id}`)
}

/**
 * Creates, updates or clears a day's note (`PUT /api/time-entries/day-notes`).
 * A note that trims to empty deletes it server-side — there is no separate
 * DELETE endpoint to call for that case.
 */
export async function saveTimeEntryDayNote(
  payload: TimeEntryDayNotePayload,
): Promise<TimeEntryDayNoteResponse> {
  const { data } = await apiClient.put<ApiResponse<TimeEntryDayNoteResponse>>(
    '/time-entries/day-notes',
    payload,
  )
  return data.data
}

/** Fetches the Segnatempo section of a Task detail (`GET /api/tasks/{task}/time-entries`, D-9). */
export async function fetchTaskTimeEntries(taskId: number): Promise<TaskTimeEntriesResponse> {
  const { data } = await apiClient.get<ApiResponse<TaskTimeEntriesResponse>>(
    `/tasks/${taskId}/time-entries`,
  )
  return data.data
}

/**
 * Creates a time entry from the Task detail (`POST /api/tasks/{task}/time-entries`, D-9).
 * The user is the actor and title/links come from the Task — the payload
 * carries neither.
 */
export async function createTaskTimeEntry(
  taskId: number,
  payload: CreateTaskTimeEntryPayload,
): Promise<TimeEntry> {
  const { data } = await apiClient.post<ApiResponse<TimeEntry>>(
    `/tasks/${taskId}/time-entries`,
    payload,
  )
  return data.data
}

/** Fallback filename when the response carries no `Content-Disposition` header. */
function fallbackFilteredExportFileName(params: FilteredTimeEntriesExportParams): string {
  return `segnatempo_${params.date_from ?? ''}_${params.date_to ?? ''}.xlsx`
}

/** Fallback filename for the monthly report (mirrors the backend's own naming, D-12). */
function fallbackMonthlyExportFileName(params: MonthlyTimeEntriesExportParams): string {
  return `report_segnatempo_${params.year}${String(params.month).padStart(2, '0')}.xlsx`
}

/**
 * Downloads the filtered xlsx export (`GET /api/time-entries/exports/filtered`, D-12): a
 * single user, one row per time entry, every dashboard filter applied (D-3 b).
 * Returns the blob and its filename; the caller triggers the actual save
 * (`saveBlob` from `@/lib/download`) so it controls the success toast timing.
 */
export async function fetchFilteredTimeEntriesExport(
  params: FilteredTimeEntriesExportParams,
): Promise<{ blob: Blob; fileName: string }> {
  const response = await apiClient.get<Blob>('/time-entries/exports/filtered', {
    params,
    ...ARRAY_PARAMS_CONFIG,
    responseType: 'blob',
  })
  const fileName =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    fallbackFilteredExportFileName(params)
  return { blob: response.data, fileName }
}

/**
 * Downloads the monthly xlsx report (`GET /api/time-entries/exports/monthly`, D-12): ignores
 * the dashboard filters, one sheet per user. `user_ids` omitted = every user
 * with a time entry that month.
 */
export async function fetchMonthlyTimeEntriesExport(
  params: MonthlyTimeEntriesExportParams,
): Promise<{ blob: Blob; fileName: string }> {
  const { month, year, user_ids } = params
  const response = await apiClient.get<Blob>('/time-entries/exports/monthly', {
    params: {
      month,
      year,
      ...(user_ids && user_ids.length > 0 ? { user_ids } : {}),
    },
    ...ARRAY_PARAMS_CONFIG,
    responseType: 'blob',
  })
  const fileName =
    filenameFromContentDisposition(response.headers['content-disposition']) ??
    fallbackMonthlyExportFileName(params)
  return { blob: response.data, fileName }
}
