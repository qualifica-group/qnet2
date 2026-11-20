import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'

export interface ActiveIntegrations {
  active: string[]
}

/** GET /api/integrations: keys of the integrations enabled on this installation. */
export async function fetchActiveIntegrations(): Promise<ActiveIntegrations> {
  const { data } = await apiClient.get<ApiResponse<ActiveIntegrations>>('/integrations')
  return data.data
}
