import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { saveBlob } from '@/lib/download'
import type {
  ApiClient,
  ApiClientPayload,
  ApiClientWithKey,
  ApiDocKind,
  ApiDocsStatus,
} from '@/features/api-integrations/types'
import type { OpenApiDocument } from '@/features/api-integrations/openapi-types'

export const API_DOC_FILENAMES: Record<ApiDocKind, string> = {
  openapi: 'qnet-api.openapi.json',
  postman: 'qnet-api.postman_collection.json',
}

export async function fetchApiClient(id: number): Promise<ApiClient> {
  const { data } = await apiClient.get<ApiResponse<ApiClient>>(`/api-clients/${id}`)
  return data.data
}

export async function createApiClient(payload: ApiClientPayload): Promise<ApiClientWithKey> {
  const { data } = await apiClient.post<ApiResponse<ApiClientWithKey>>('/api-clients', payload)
  return data.data
}

export async function updateApiClient(id: number, payload: ApiClientPayload): Promise<ApiClient> {
  const { data } = await apiClient.patch<ApiResponse<ApiClient>>(`/api-clients/${id}`, payload)
  return data.data
}

/** Revokes every previous key of the client and returns the single new one. */
export async function rotateApiClientKey(id: number): Promise<ApiClientWithKey> {
  const { data } = await apiClient.post<ApiResponse<ApiClientWithKey>>(
    `/api-clients/${id}/rotate-key`,
  )
  return data.data
}

export async function deleteApiClient(id: number): Promise<void> {
  await apiClient.delete(`/api-clients/${id}`)
}

/**
 * GET /api/api-clients/docs/openapi — the raw OpenAPI document (not enveloped).
 * 404 while no document was generated yet.
 */
export async function fetchOpenApiDocument(): Promise<OpenApiDocument> {
  const { data } = await apiClient.get<OpenApiDocument>('/api-clients/docs/openapi')
  return data
}

/** GET /api/api-clients/docs/status — whether a document exists and whether a regeneration runs. */
export async function fetchApiDocsStatus(): Promise<ApiDocsStatus> {
  const { data } = await apiClient.get<ApiResponse<ApiDocsStatus>>('/api-clients/docs/status')
  return data.data
}

/** POST /api/api-clients/docs/refresh — starts a background regeneration (202). */
export async function refreshApiDocs(): Promise<ApiDocsStatus> {
  const { data } = await apiClient.post<ApiResponse<ApiDocsStatus>>('/api-clients/docs/refresh')
  return data.data
}

/** Downloads the OpenAPI document or the Postman collection under its fixed filename. */
export async function downloadApiDoc(kind: ApiDocKind): Promise<void> {
  const response = await apiClient.get<Blob>(`/api-clients/docs/${kind}`, { responseType: 'blob' })
  saveBlob(response.data, API_DOC_FILENAMES[kind])
}
