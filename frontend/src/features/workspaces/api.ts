import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  AvailableWorkspaces,
  NavigationCatalogueEntry,
  Workspace,
  WorkspacePayload,
} from '@/features/workspaces/types'

export async function fetchAvailableWorkspaces(): Promise<AvailableWorkspaces> {
  const { data } = await apiClient.get<ApiResponse<AvailableWorkspaces>>('/workspaces/available')
  return data.data
}

export async function fetchWorkspaces(): Promise<Workspace[]> {
  const { data } = await apiClient.get<ApiResponse<Workspace[]>>('/workspaces')
  return data.data
}

export async function fetchNavigationCatalogue(): Promise<NavigationCatalogueEntry[]> {
  const { data } = await apiClient.get<ApiResponse<NavigationCatalogueEntry[]>>(
    '/workspaces/navigation-catalogue',
  )
  return data.data
}

export async function createWorkspace(payload: WorkspacePayload): Promise<Workspace> {
  const { data } = await apiClient.post<ApiResponse<Workspace>>('/workspaces', payload)
  return data.data
}

export async function updateWorkspace(id: number, payload: WorkspacePayload): Promise<Workspace> {
  const { data } = await apiClient.put<ApiResponse<Workspace>>(`/workspaces/${id}`, payload)
  return data.data
}

export async function deleteWorkspace(id: number): Promise<void> {
  await apiClient.delete(`/workspaces/${id}`)
}
