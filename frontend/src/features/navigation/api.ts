import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { NavigationItem } from '@/features/navigation/types'

/** `workspace` = app slug (spec 0317); null = the full menu. */
export async function fetchNavigation(workspace: string | null = null): Promise<NavigationItem[]> {
  const { data } = await apiClient.get<ApiResponse<NavigationItem[]>>('/navigation', {
    params: workspace ? { workspace } : undefined,
  })
  return data.data
}
