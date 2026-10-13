import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  BusinessFunctionDetail,
  BusinessFunctionDetailWithPermissions,
  CreateBusinessFunctionPayload,
  UpdateBusinessFunctionPayload,
} from '@/features/business-functions/types'

/** Query key of a single business function's detail, shared by the detail screen and its in-place saves. */
export function businessFunctionDetailQueryKey(id: number) {
  return ['business-functions', 'detail', id] as const
}

/**
 * Fetches a single business function detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchBusinessFunction(
  id: number,
): Promise<BusinessFunctionDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<BusinessFunctionDetail, ResourcePermissions>
  >(`/business-functions/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a business function. Returns the created resource from the envelope `data`. */
export async function createBusinessFunction(
  payload: CreateBusinessFunctionPayload,
): Promise<BusinessFunctionDetail> {
  const { data } = await apiClient.post<ApiResponse<BusinessFunctionDetail>>(
    '/business-functions',
    payload,
  )
  return data.data
}

/**
 * Partially updates a business function (PATCH). Returns the saved record with
 * the actor's permissions, so the in-place detail re-syncs on it as is.
 */
export async function updateBusinessFunction(
  id: number,
  payload: UpdateBusinessFunctionPayload,
): Promise<BusinessFunctionDetailWithPermissions> {
  const { data } = await apiClient.patch<
    ApiResponseWithPermissions<BusinessFunctionDetail, ResourcePermissions>
  >(`/business-functions/${id}`, payload)
  return { ...data.data, permissions: data.permissions }
}

/** Deletes a business function. Backend responds 204 with no body. */
export async function deleteBusinessFunction(id: number): Promise<void> {
  await apiClient.delete(`/business-functions/${id}`)
}
