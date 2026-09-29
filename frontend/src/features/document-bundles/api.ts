import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateDocumentBundlePayload,
  DocumentBundle,
  DocumentBundleWithPermissions,
  UpdateDocumentBundlePayload,
} from '@/features/document-bundles/types'

/**
 * Polymorphic owner alias sent as `attachable_type` to `/api/attachments*`
 * (`config('attachments.attachable_types')`) — NOT the plural table-registry
 * resource key (`document-bundles`) the activity log/table use. Mirrors
 * `REGISTRY_ATTACHABLE_ALIAS`.
 */
export const DOCUMENT_BUNDLE_ATTACHABLE_ALIAS = 'document_bundle'

/**
 * Fetches a single document bundle detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchDocumentBundle(id: number): Promise<DocumentBundleWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<DocumentBundle, ResourcePermissions>>(
    `/document-bundles/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Creates a document bundle. Returns the created resource from the envelope `data`. */
export async function createDocumentBundle(payload: CreateDocumentBundlePayload): Promise<DocumentBundle> {
  const { data } = await apiClient.post<ApiResponse<DocumentBundle>>('/document-bundles', payload)
  return data.data
}

/** Partially updates a document bundle (PATCH). Returns the updated resource. */
export async function updateDocumentBundle(
  id: number,
  payload: UpdateDocumentBundlePayload,
): Promise<DocumentBundle> {
  const { data } = await apiClient.patch<ApiResponse<DocumentBundle>>(`/document-bundles/${id}`, payload)
  return data.data
}

/** Deletes a document bundle. Backend responds 204 with no body. */
export async function deleteDocumentBundle(id: number): Promise<void> {
  await apiClient.delete(`/document-bundles/${id}`)
}
