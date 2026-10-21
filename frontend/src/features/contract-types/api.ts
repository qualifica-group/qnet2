import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  CONTRACT_TYPES_DOMAIN,
  type ContractType,
  type ContractTypeDetail,
  type ContractTypePayload,
  type UpdateContractTypePayload,
} from '@/features/contract-types/types'

/** Centralized query keys of the contract-types catalogue. */
export const contractTypeKeys = {
  all: ['contract-types'] as const,
  detail: (id: number) => ['contract-types', 'detail', id] as const,
}

const ENDPOINT = `/${CONTRACT_TYPES_DOMAIN}`

/** Read-only fallback when the envelope carries no authorization metadata: the backend stays the authority. */
const VIEW_ONLY_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: false, update: false, delete: false, export: false, import: false },
  fields: {},
  actions: {},
}

type PermissionsEnvelope = ApiResponseWithPermissions<ContractType, ResourcePermissions | undefined>

function withPermissions({ data, permissions }: PermissionsEnvelope): ContractTypeDetail {
  return { ...data, permissions: permissions ?? VIEW_ONLY_PERMISSIONS }
}

/** Fetches a contract type together with the actor's authorization metadata for it. */
export async function fetchContractType(id: number): Promise<ContractTypeDetail> {
  const { data } = await apiClient.get<PermissionsEnvelope>(`${ENDPOINT}/${id}`)
  return withPermissions(data)
}

export async function createContractType(payload: ContractTypePayload): Promise<ContractType> {
  const { data } = await apiClient.post<ApiResponse<ContractType>>(ENDPOINT, payload)
  return data.data
}

/** Partial update (PATCH): the in-place detail saves one field at a time. */
export async function updateContractType(id: number, payload: UpdateContractTypePayload): Promise<ContractTypeDetail> {
  const { data } = await apiClient.patch<PermissionsEnvelope>(`${ENDPOINT}/${id}`, payload)
  return withPermissions(data)
}

/** 409 when a condition already uses the type: the caller maps it to "deactivate it instead". */
export async function deleteContractType(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
