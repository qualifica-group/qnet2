import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useAuth } from '@/features/auth/use-auth'
import { fetchAvailableWorkspaces } from '@/features/workspaces/api'
import { workspaceKeys } from '@/features/workspaces/query-keys'
import type { AvailableWorkspaces } from '@/features/workspaces/types'

export function useAvailableWorkspaces() {
  const { isAuthenticated } = useAuth()

  return useQuery<AvailableWorkspaces, AxiosError>({
    queryKey: workspaceKeys.available(),
    queryFn: fetchAvailableWorkspaces,
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })
}
