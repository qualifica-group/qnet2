import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateSectorPayload,
  SectorDetail,
  SectorDetailWithPermissions,
  SectorTreeNode,
  UpdateSectorPayload,
} from '@/features/sectors/types'

/** Fetches the full sector tree (roots → descendants), used by the parent picker. */
export async function fetchSectorTree(): Promise<SectorTreeNode[]> {
  const { data } = await apiClient.get<ApiResponse<SectorTreeNode[]>>('/sectors/tree')
  return data.data
}

/**
 * Fetches a single sector detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchSector(id: number): Promise<SectorDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<SectorDetail, ResourcePermissions>
  >(`/sectors/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a sector. Returns the created resource from the envelope `data`. */
export async function createSector(payload: CreateSectorPayload): Promise<SectorDetail> {
  const { data } = await apiClient.post<ApiResponse<SectorDetail>>('/sectors', payload)
  return data.data
}

/**
 * Partially updates a sector (PATCH). Returns the saved record with the
 * actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateSector(
  id: number,
  payload: UpdateSectorPayload,
): Promise<SectorDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<SectorDetail, ResourcePermissions>
  >(`/sectors/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a sector. Backend responds 204 with no body (409 if it has children). */
export async function deleteSector(id: number): Promise<void> {
  await apiClient.delete(`/sectors/${id}`)
}
