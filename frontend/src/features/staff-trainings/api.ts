import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { filenameFromContentDisposition, normalizeBlobError } from '@/lib/download'
import {
  STAFF_TRAININGS_DOMAIN,
  type Compliance,
  type ComplianceParams,
  type StaffTraining,
  type StaffTrainingPayload,
} from '@/features/staff-trainings/types'

const ENDPOINT = `/${STAFF_TRAININGS_DOMAIN}`

export async function fetchStaffTraining(id: number): Promise<StaffTraining> {
  const { data } = await apiClient.get<ApiResponse<StaffTraining>>(`${ENDPOINT}/${id}`)
  return data.data
}

export async function createStaffTraining(payload: StaffTrainingPayload): Promise<StaffTraining> {
  const { data } = await apiClient.post<ApiResponse<StaffTraining>>(ENDPOINT, payload)
  return data.data
}

export async function updateStaffTraining(id: number, payload: StaffTrainingPayload): Promise<StaffTraining> {
  const { data } = await apiClient.patch<ApiResponse<StaffTraining>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

export async function deleteStaffTraining(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}

/** Uploads (or replaces) the single certificate; no `Content-Type` is forced so axios adds the boundary. */
export async function uploadStaffTrainingAttachment(id: number, file: File): Promise<StaffTraining> {
  const formData = new FormData()
  formData.append('file', file)
  const { data } = await apiClient.post<ApiResponse<StaffTraining>>(`${ENDPOINT}/${id}/attachment`, formData)
  return data.data
}

export async function deleteStaffTrainingAttachment(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}/attachment`)
}

export interface StaffTrainingAttachmentFile {
  blob: Blob
  filename: string | null
}

/** Authenticated download of the certificate (the endpoint streams the file). */
export async function downloadStaffTrainingAttachment(id: number): Promise<StaffTrainingAttachmentFile> {
  try {
    const response = await apiClient.get<Blob>(`${ENDPOINT}/${id}/attachment`, { responseType: 'blob' })
    return {
      blob: response.data,
      filename: filenameFromContentDisposition(response.headers['content-disposition']),
    }
  } catch (error) {
    throw await normalizeBlobError(error)
  }
}

/** Person x course status of the people the actor's visibility reveals. */
export async function fetchCompliance(params: ComplianceParams): Promise<Compliance> {
  const { data } = await apiClient.get<ApiResponse<Compliance>>(`${ENDPOINT}/compliance`, { params })
  return data.data
}
