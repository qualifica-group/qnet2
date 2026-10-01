import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { SystemHealthData } from '@/features/system-health/types'

/** GET /api/system-health — super-admin only (server-enforced). */
export async function fetchSystemHealth(): Promise<SystemHealthData> {
  const { data } = await apiClient.get<ApiResponse<SystemHealthData>>('/system-health')
  return data.data
}

/** POST /api/presence/heartbeat — 204, refreshes the token's `last_used_at`. */
export async function sendPresenceHeartbeat(): Promise<void> {
  await apiClient.post('/presence/heartbeat')
}
