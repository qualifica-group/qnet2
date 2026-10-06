import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  CreateRegistryPayload,
  RegistryDetail,
  RegistryDetailWithPermissions,
  UpdateRegistryPayload,
} from '@/features/registries/types'

/**
 * Attachable alias of a registry's documents (spec 0173): the value
 * `config/attachments.php` maps to `Registry`, identical to the record's
 * morph identity. Mirrors `WORK_ORDER_ATTACHABLE_ALIAS`.
 */
export const REGISTRY_ATTACHABLE_ALIAS = 'registry'

/**
 * Query key of a single registry's detail (fresh-on-open pattern). Shared by
 * the detail screens and by the post-mutation invalidation, so they can
 * never drift apart. `null` (an unparsable route param) is a key that is never
 * fetched.
 */
export function registryDetailQueryKey(id: number | null) {
  return ['registries', 'detail', id] as const
}

/**
 * Fetches a single registry detail together with the actor's authorization
 * metadata for it (`permissions`, a top-level envelope sibling of `data`).
 */
export async function fetchRegistry(id: number): Promise<RegistryDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<RegistryDetail, ResourcePermissions>
  >(`/registries/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a registry. Returns the created resource from the envelope `data`. */
export async function createRegistry(
  payload: CreateRegistryPayload,
): Promise<RegistryDetail> {
  const { data } = await apiClient.post<ApiResponse<RegistryDetail>>('/registries', payload)
  return data.data
}

/**
 * Partially updates a registry (PATCH). Returns the updated resource with the
 * actor's `permissions` (the server answers `okWithPermissions`), so the
 * in-place detail can seed its cache with the full shape (spec 0200).
 */
export async function updateRegistry(
  id: number,
  payload: UpdateRegistryPayload,
): Promise<RegistryDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<RegistryDetail, ResourcePermissions>
  >(`/registries/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a registry. Backend responds 204 with no body. */
export async function deleteRegistry(id: number): Promise<void> {
  await apiClient.delete(`/registries/${id}`)
}
