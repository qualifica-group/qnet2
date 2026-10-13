import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import { normalizeBlobError, saveBlob } from '@/lib/download'
import type {
  ApiClient,
  ApiClientPayload,
  ApiClientWithKey,
  ApiDocDownloadResult,
  ApiDocKind,
} from '@/features/api-integrations/types'
import type { OpenApiDocument, OpenApiFetchResult } from '@/features/api-integrations/openapi-types'

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

const GENERATING_STATUS = 202
const DEFAULT_RETRY_AFTER_SECONDS = 5

/** `Retry-After` in seconds; anything unusable falls back to the default. */
function parseRetryAfter(value: unknown): number {
  const seconds = Number(value)
  return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : DEFAULT_RETRY_AFTER_SECONDS
}

/**
 * GET /api/api-clients/docs/openapi — the raw OpenAPI document (not enveloped),
 * or 202 `{ data: { status: 'generating' } }` while the server builds it.
 */
export async function fetchOpenApiDocument(): Promise<OpenApiFetchResult> {
  const response = await apiClient.get<OpenApiDocument>('/api-clients/docs/openapi')
  if (response.status === GENERATING_STATUS) {
    return { status: 'generating', retryAfterSeconds: parseRetryAfter(response.headers['retry-after']) }
  }
  return { status: 'ready', document: response.data }
}

/**
 * Downloads the OpenAPI document or the Postman collection under its fixed
 * filename; 'generating' when the server answered 202 and there is no file yet.
 */
export async function downloadApiDoc(kind: ApiDocKind): Promise<ApiDocDownloadResult> {
  try {
    const response = await apiClient.get<Blob>(`/api-clients/docs/${kind}`, { responseType: 'blob' })
    if (response.status === GENERATING_STATUS) {
      return 'generating'
    }
    saveBlob(response.data, API_DOC_FILENAMES[kind])
    return 'saved'
  } catch (error) {
    throw await normalizeBlobError(error)
  }
}
