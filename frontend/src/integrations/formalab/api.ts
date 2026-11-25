import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  FormalabCategorySettings,
  FormalabCategorySettingsPayload,
  FormalabConnectionTest,
  FormalabStatus,
  FormalabSyncState,
  FormalabWorkOrderLink,
} from '@/integrations/formalab/types'

const BASE = '/integrations/formalab'

/** Call-log table domain (`/api/tables/formalab-call-logs/*`). */
export const FORMALAB_CALL_LOGS_DOMAIN = 'formalab-call-logs'

/** Class catalogue table domain (`/api/tables/formalab-classes/*`). */
export const FORMALAB_CLASSES_DOMAIN = 'formalab-classes'

export async function fetchFormalabStatus(): Promise<FormalabStatus> {
  const { data } = await apiClient.get<ApiResponse<FormalabStatus>>(`${BASE}/status`)
  return data.data
}

export async function fetchFormalabSyncState(): Promise<FormalabSyncState> {
  const { data } = await apiClient.get<ApiResponse<FormalabSyncState>>(`${BASE}/sync-state`)
  return data.data
}

export async function startFormalabSync(): Promise<void> {
  await apiClient.post(`${BASE}/sync`)
}

export async function testFormalabConnection(): Promise<FormalabConnectionTest> {
  const { data } = await apiClient.post<ApiResponse<FormalabConnectionTest>>(`${BASE}/test-connection`)
  return data.data
}

export async function fetchFormalabWorkOrderLink(workOrderId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.get<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}`)
  return data.data
}

/**
 * "Sincronizza": the server re-reads from Formalab the edition chosen in the
 * work order's class select and rewrites the mapped fields.
 */
export async function syncFormalabWorkOrderLink(workOrderId: number): Promise<FormalabWorkOrderLink> {
  const { data } = await apiClient.post<ApiResponse<FormalabWorkOrderLink>>(`${BASE}/work-orders/${workOrderId}/sync`)
  return data.data
}


export async function fetchFormalabCategorySettings(categoryId: number): Promise<FormalabCategorySettings> {
  const { data } = await apiClient.get<ApiResponse<FormalabCategorySettings>>(`${BASE}/product-categories/${categoryId}`)
  return data.data
}

export async function updateFormalabCategorySettings(
  categoryId: number,
  payload: FormalabCategorySettingsPayload,
): Promise<FormalabCategorySettings> {
  const { data } = await apiClient.put<ApiResponse<FormalabCategorySettings>>(`${BASE}/product-categories/${categoryId}`, payload)
  return data.data
}
