import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ContractDetail,
  ContractDetailWithPermissions,
  ReactivateContractPayload,
  ScheduleContractPayload,
  TerminateContractPayload,
  UpdateContractPayload,
  ValidateContractPayload,
} from '@/features/contracts/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const CONTRACTS_DOMAIN = 'contracts'

/** Query key of a single contract's detail (fresh-on-open pattern, mirrors `quoteDetailQueryKey`). */
export function contractDetailQueryKey(id: number | null) {
  return ['contracts', 'detail', id] as const
}

/** Fetches a single contract detail together with the actor's authorization metadata (`permissions`). */
export async function fetchContract(id: number): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

/** Partially updates a contract's editable data (PATCH). Returns the updated resource. */
export async function updateContract(
  id: number,
  payload: UpdateContractPayload,
): Promise<ContractDetail> {
  const { data } = await apiClient.patch<ApiResponse<ContractDetail>>(`/contracts/${id}`, payload)
  return data.data
}

/** "Valida contratto" (BR-3): not repeatable, 422 if already validated or suspended. */
export async function validateContract(
  id: number,
  payload: ValidateContractPayload,
): Promise<ContractDetail> {
  const { data } = await apiClient.post<ApiResponse<ContractDetail>>(`/contracts/${id}/validate`, payload)
  return data.data
}

/** "Programma contratto": persists expiry/renewal dates and the destination status. */
export async function scheduleContract(
  id: number,
  payload: ScheduleContractPayload,
): Promise<ContractDetail> {
  const { data } = await apiClient.post<ApiResponse<ContractDetail>>(`/contracts/${id}/schedule`, payload)
  return data.data
}

/** "Disdici contratto" (BR-4): moves the contract to a `closed_lost` status. */
export async function terminateContract(
  id: number,
  payload: TerminateContractPayload,
): Promise<ContractDetail> {
  const { data } = await apiClient.post<ApiResponse<ContractDetail>>(`/contracts/${id}/terminate`, payload)
  return data.data
}

/**
 * "Riattiva contratto" (BR-2): empty body on a suspended contract (the
 * pre-suspension status is restored server-side), destination status on a
 * disdetto one.
 */
export async function reactivateContract(
  id: number,
  payload: ReactivateContractPayload = {},
): Promise<ContractDetail> {
  const { data } = await apiClient.post<ApiResponse<ContractDetail>>(`/contracts/${id}/reactivate`, payload)
  return data.data
}
