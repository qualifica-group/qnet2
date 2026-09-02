import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import type {
  ChangeContractStatusPayload,
  ContractDetail,
  ContractDetailWithPermissions,
  ContractProgrammableLine,
  CreateContractWorkOrderPayload,
  ReactivateContractPayload,
  TerminateContractPayload,
  UpdateContractPayload,
  ValidateContractPayload,
} from '@/features/contracts/types'
import type { WorkOrderDetail, WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Table/stats domain key of this module, shared by the table adapter. */
export const CONTRACTS_DOMAIN = 'contracts'

/**
 * Polymorphic owner alias of a contract (`config('attachments.attachable_types')`),
 * sent as `attachable_type` by the documents surface — singular, NOT the plural
 * domain key above. Mirrors `OPPORTUNITY_ATTACHABLE_ALIAS`.
 */
export const CONTRACT_ATTACHABLE_ALIAS = 'contract'

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
 * "Riapri contratto" (BR-2): empty body on a suspended contract (the
 * pre-suspension status is restored server-side), destination status on a
 * closed one — su entrambi i lati della chiusura (rev.3).
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

/**
 * "Programma" (spec 0095 D-6): REVENUE lines of the contract's offer, each
 * with its work-order occupation, feeding the `ContractProgramDialog`'s
 * selectable table. Gate: `contracts.program`.
 */
export async function fetchContractProgrammableLines(
  contractId: number,
): Promise<ContractProgrammableLine[]> {
  const { data } = await apiClient.get<ApiResponse<ContractProgrammableLine[]>>(
    `/contracts/${contractId}/programmable-lines`,
  )
  return data.data
}

/**
 * "Programma": generates ONE work order from the selected offer lines
 * (spec 0095 D-6). Returns the same `WorkOrderDetailWithPermissions` shape
 * `POST /api/work-orders` returns (AC-035): no second Commessa shape.
 */
export async function createContractWorkOrder(
  contractId: number,
  payload: CreateContractWorkOrderPayload,
): Promise<WorkOrderDetailWithPermissions> {
  const { data } = await apiClient.post<ApiResponseWithPermissions<WorkOrderDetail, ResourcePermissions>>(
    `/contracts/${contractId}/work-orders`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}
