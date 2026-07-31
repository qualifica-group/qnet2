import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ContractStatusDetail,
  ContractStatusDetailWithPermissions,
  CreateContractStatusPayload,
  UpdateContractStatusPayload,
} from '@/features/contract-statuses/types'

/**
 * Fetches a single contract status detail together with the actor's
 * authorization metadata for it (`permissions`, a top-level envelope sibling
 * of `data`).
 */
export async function fetchContractStatus(id: number): Promise<ContractStatusDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<ContractStatusDetail, ResourcePermissions>
  >(`/contract-statuses/${id}`)
  return { ...data.data, permissions: data.permissions }
}

/** Creates a contract status. Returns the created resource from the envelope `data`. */
export async function createContractStatus(
  payload: CreateContractStatusPayload,
): Promise<ContractStatusDetail> {
  const { data } = await apiClient.post<ApiResponse<ContractStatusDetail>>(
    '/contract-statuses',
    payload,
  )
  return data.data
}

/** Partially updates a contract status (PATCH). Returns the updated resource. */
export async function updateContractStatus(
  id: number,
  payload: UpdateContractStatusPayload,
): Promise<ContractStatusDetail> {
  const { data } = await apiClient.patch<ApiResponse<ContractStatusDetail>>(
    `/contract-statuses/${id}`,
    payload,
  )
  return data.data
}

/**
 * Deletes a contract status. Backend responds 200 with no data, or 409 when
 * the status is still referenced by a Contract — the caller surfaces the
 * backend's exact `message` for that case.
 */
export async function deleteContractStatus(id: number): Promise<void> {
  await apiClient.delete(`/contract-statuses/${id}`)
}
