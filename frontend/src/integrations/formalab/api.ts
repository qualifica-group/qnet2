import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  FormalabClass,
  FormalabConnectionTest,
  FormalabLinkSummary,
  FormalabStatus,
  FormalabWorkOrderLink,
} from '@/integrations/formalab/types'

const BASE = '/integrations/formalab'

/** Call-log table domain (`/api/tables/formalab-call-logs/*`). */
export const FORMALAB_CALL_LOGS_DOMAIN = 'formalab-call-logs'

export const FORMALAB_CLASSES_LIMIT = 20

export async function fetchFormalabStatus(): Promise<FormalabStatus> {
  const { data } = await apiClient.get<ApiResponse<FormalabStatus>>(`${BASE}/status`)
  return data.data
}

export async function startFormalabSync(): Promise<void> {
  await apiClient.post(`${BASE}/sync`)
}

export async function testFormalabConnection(): Promise<FormalabConnectionTest> {
  const { data } = await apiClient.post<ApiResponse<FormalabConnectionTest>>(`${BASE}/test-connection`)
  return data.data
}

export async function linkFormalabWorkOrders(): Promise<FormalabLinkSummary> {
  const { data } = await apiClient.post<ApiResponse<FormalabLinkSummary>>(`${BASE}/link-work-orders`)
  return data.data
}

export async function fetchFormalabClasses(search: string): Promise<FormalabClass[]> {
  const { data } = await apiClient.get<ApiResponse<FormalabClass[]>>(`${BASE}/classes`, {
    params: { search, limit: FORMALAB_CLASSES_LIMIT },
  })
  return data.data
}

export async function fetchFormalabWorkOrderLink(workOrderId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.get<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}`)
  return data.data
}

export async function setFormalabWorkOrderLink(workOrderId: number, editionId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.put<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}`, {
    edition_id: editionId,
  })
  return data.data
}

/** Re-reads the linked edition from Formalab now and rewrites the course fields. */
export async function syncFormalabWorkOrderLink(workOrderId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.post<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}/sync`)
  return data.data
}

export async function removeFormalabWorkOrderLink(workOrderId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.delete<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}`)
  return data.data
}
