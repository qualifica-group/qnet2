import { apiClient } from '@/api/client'
import type { ApiResponse, ApiResponseWithPermissions } from '@/api/types'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  AGENT_FISCAL_PROFILES_DOMAIN,
  type AgentFiscalProfileDetail,
  type AgentFiscalProfileDetailWithPermissions,
  type AgentFiscalProfilePayload,
} from '@/features/agent-fiscal-profiles/types'

const ENDPOINT = `/${AGENT_FISCAL_PROFILES_DOMAIN}`

/** Centralized query keys of the agent-fiscal-profiles feature. */
export const agentFiscalProfileKeys = {
  all: [AGENT_FISCAL_PROFILES_DOMAIN] as const,
  detail: (id: number) => [AGENT_FISCAL_PROFILES_DOMAIN, 'detail', id] as const,
}

/** Fetches a profile together with the actor's authorization metadata for it. */
export async function fetchAgentFiscalProfile(id: number): Promise<AgentFiscalProfileDetailWithPermissions> {
  const { data } = await apiClient.get<ApiResponseWithPermissions<AgentFiscalProfileDetail, ResourcePermissions>>(
    `${ENDPOINT}/${id}`,
  )
  return { ...data.data, permissions: data.permissions }
}

export async function createAgentFiscalProfile(payload: AgentFiscalProfilePayload): Promise<AgentFiscalProfileDetail> {
  const { data } = await apiClient.post<ApiResponse<AgentFiscalProfileDetail>>(ENDPOINT, payload)
  return data.data
}

/** Partial PATCH (spec 0264 D-1): fields left out keep their saved value. */
export async function updateAgentFiscalProfile(
  id: number,
  payload: Partial<AgentFiscalProfilePayload>,
): Promise<AgentFiscalProfileDetailWithPermissions> {
  const { data } = await apiClient.patch<ApiResponseWithPermissions<AgentFiscalProfileDetail, ResourcePermissions>>(
    `${ENDPOINT}/${id}`,
    payload,
  )
  return { ...data.data, permissions: data.permissions }
}

/** 204 without body. */
export async function deleteAgentFiscalProfile(id: number): Promise<void> {
  await apiClient.delete(`${ENDPOINT}/${id}`)
}
