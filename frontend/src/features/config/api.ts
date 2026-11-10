import { apiClient } from '@/api/client'
import { BOOT_REQUEST_TIMEOUT_MS } from '@/api/boot-request'
import type { ApiResponse } from '@/api/types'
import type { AppConfig } from '@/features/config/types'

/**
 * Fetches the public application bootstrap config (enum options, ...). The
 * endpoint is unauthenticated; the standard envelope is unwrapped to `data`.
 */
export async function fetchConfig(): Promise<AppConfig> {
  const { data } = await apiClient.get<ApiResponse<AppConfig>>('/config', {
    timeout: BOOT_REQUEST_TIMEOUT_MS,
  })
  return data.data
}
