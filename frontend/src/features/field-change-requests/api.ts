import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  CreateFieldChangeRequestPayload,
  FieldChangeRequestResource,
} from '@/features/field-change-requests/types'

/** Creates a field change request. Returns the created resource from the envelope `data`. */
export async function createFieldChangeRequest(
  payload: CreateFieldChangeRequestPayload,
): Promise<FieldChangeRequestResource> {
  const { data } = await apiClient.post<ApiResponse<FieldChangeRequestResource>>(
    '/field-change-requests',
    payload,
  )
  return data.data
}

/** Fetches a single field change request detail. */
export async function fetchFieldChangeRequest(id: number): Promise<FieldChangeRequestResource> {
  const { data } = await apiClient.get<ApiResponse<FieldChangeRequestResource>>(
    `/field-change-requests/${id}`,
  )
  return data.data
}

/**
 * Fetches every field change request for a given record, `pending` first
 * (server-ordered). Feeds the reusable "change requests" section of a
 * record's work panel.
 */
export async function fetchFieldChangeRequestsForRecord(
  resource: string,
  subjectId: number,
): Promise<FieldChangeRequestResource[]> {
  const { data } = await apiClient.get<ApiResponse<FieldChangeRequestResource[]>>(
    '/field-change-requests/for-record',
    { params: { resource, subject_id: subjectId } },
  )
  return data.data
}

/** Approves a pending request. The value is applied to the record in the same transaction (D-7). */
export async function approveFieldChangeRequest(
  id: number,
  note?: string | null,
): Promise<FieldChangeRequestResource> {
  const { data } = await apiClient.post<ApiResponse<FieldChangeRequestResource>>(
    `/field-change-requests/${id}/approve`,
    { note },
  )
  return data.data
}

/** Rejects a pending request. The record is left untouched. */
export async function rejectFieldChangeRequest(
  id: number,
  note?: string | null,
): Promise<FieldChangeRequestResource> {
  const { data } = await apiClient.post<ApiResponse<FieldChangeRequestResource>>(
    `/field-change-requests/${id}/reject`,
    { note },
  )
  return data.data
}
