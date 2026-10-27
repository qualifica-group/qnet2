import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { navigationKeys } from '@/features/navigation/use-navigation'
import {
  createWorkspace,
  deleteWorkspace,
  fetchNavigationCatalogue,
  fetchWorkspaces,
  updateWorkspace,
} from '@/features/workspaces/api'
import { workspaceKeys } from '@/features/workspaces/query-keys'
import type {
  NavigationCatalogueEntry,
  Workspace,
  WorkspacePayload,
} from '@/features/workspaces/types'

type ApiError = AxiosError<ApiErrorResponse>

export function useWorkspaces() {
  return useQuery<Workspace[], ApiError>({
    queryKey: workspaceKeys.admin(),
    queryFn: fetchWorkspaces,
  })
}

export function useNavigationCatalogue() {
  return useQuery<NavigationCatalogueEntry[], ApiError>({
    queryKey: workspaceKeys.catalogue(),
    queryFn: fetchNavigationCatalogue,
  })
}

/** Apps feed the launcher and the menu of every user: both caches go stale after any change. */
function useInvalidateWorkspaces() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: workspaceKeys.all }),
      queryClient.invalidateQueries({ queryKey: navigationKeys.all }),
    ])
}

export function useCreateWorkspace() {
  const invalidate = useInvalidateWorkspaces()
  return useMutation<Workspace, ApiError, WorkspacePayload>({
    mutationFn: (payload) => createWorkspace(payload),
    onSuccess: invalidate,
  })
}

export function useUpdateWorkspace(id: number) {
  const invalidate = useInvalidateWorkspaces()
  return useMutation<Workspace, ApiError, WorkspacePayload>({
    mutationFn: (payload) => updateWorkspace(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteWorkspace() {
  const invalidate = useInvalidateWorkspaces()
  return useMutation<void, ApiError, number>({
    mutationFn: (id) => deleteWorkspace(id),
    onSuccess: invalidate,
  })
}
