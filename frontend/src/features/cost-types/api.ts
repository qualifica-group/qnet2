import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  COST_TYPES_DOMAIN,
  type CostTypeDetail,
  type CostTypeDetailWithPermissions,
  type CostTypePayload,
} from '@/features/cost-types/types'

const ENDPOINT = `/${COST_TYPES_DOMAIN}`

/** Centralized query keys of the cost-types feature. */
export const costTypeKeys = {
  all: [COST_TYPES_DOMAIN] as const,
  detail: (id: number) => [COST_TYPES_DOMAIN, 'detail', id] as const,
}

/** Permissions derived from the instance abilities, used when the envelope carries none. */
function permissionsFromAbilities(abilities: CostTypeDetail['abilities']): ResourcePermissions {
  return {
    resource: {
      view: true,
      create: false,
      update: abilities.update,
      delete: abilities.delete,
      export: false,
      import: false,
    },
    fields: {},
    actions: {},
  }
}

/** Fetches a cost type together with the actor's authorization metadata for it. */
export async function fetchCostType(id: number): Promise<CostTypeDetailWithPermissions> {
  const { data } = await apiClient.get<
    ApiResponseWithPermissions<CostTypeDetail, ResourcePermissions | undefined>
  >(`${ENDPOINT}/${id}`)
  return { ...data.data, permissions: data.permissions ?? permissionsFromAbilities(data.data.abilities) }
}

export async function createCostType(payload: CostTypePayload): Promise<CostTypeDetail> {
  const { data } = await apiClient.post<ApiResponse<CostTypeDetail>>(ENDPOINT, payload)
  return data.data
}

export async function updateCostType(id: number, payload: CostTypePayload): Promise<CostTypeDetail> {
  const { data } = await apiClient.put<ApiResponse<CostTypeDetail>>(`${ENDPOINT}/${id}`, payload)
  return data.data
}

/** 409 when the cost type is used by purchase order lines: the caller maps it. */
export async function deleteCostType(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
