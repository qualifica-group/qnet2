import { apiClient } from '@/api/client'
import type { ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ChangeContractStatusPayload,
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

/**
 * Every write below returns the contract WITH its fresh `permissions`
 * envelope, not just `data` — the action flags depend on the contract's own
 * status group (direttiva 2026-08-31 rev.2), so a caller that kept the old
 * `permissions` would keep rendering the previous state's buttons until a
 * reload. `okWithPermissions` on the backend already sends them on every one
 * of these endpoints.
 */
function withPermissions(
  data: ApiResponseWithPermissions<ContractDetail, ResourcePermissions>,
): ContractDetailWithPermissions {
  return { ...data.data, permissions: data.permissions }
}

/** Partially updates a contract's editable data (PATCH). Returns the updated resource. */
export async function updateContract(
  id: number,
  payload: UpdateContractPayload,
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}`,
    payload,
  )
  return withPermissions(data)
}

/** "Valida contratto" (BR-3): not repeatable, 422 if already validated or suspended. */
export async function validateContract(
  id: number,
  payload: ValidateContractPayload,
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}/validate`,
    payload,
  )
  return withPermissions(data)
}

/** "Modifica stato": moves the contract onto another open/pending status. */
export async function changeContractStatus(
  id: number,
  payload: ChangeContractStatusPayload,
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}/change-status`,
    payload,
  )
  return withPermissions(data)
}

/** "Programma contratto": persists expiry/renewal dates and the destination status. */
export async function scheduleContract(
  id: number,
  payload: ScheduleContractPayload,
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}/schedule`,
    payload,
  )
  return withPermissions(data)
}

/** "Disdici contratto" (BR-4): moves the contract to a `closed_lost` status. */
export async function terminateContract(
  id: number,
  payload: TerminateContractPayload,
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}/terminate`,
    payload,
  )
  return withPermissions(data)
}

/**
 * "Riattiva contratto" (BR-2): empty body on a suspended contract (the
 * pre-suspension status is restored server-side), destination status on a
 * disdetto one.
 */
export async function reactivateContract(
  id: number,
  payload: ReactivateContractPayload = {},
): Promise<ContractDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<ContractDetail, ResourcePermissions>>(
    `/contracts/${id}/reactivate`,
    payload,
  )
  return withPermissions(data)
}
