import { useAvailableWorkspaces } from '@/features/workspaces/use-available-workspaces'
import type { AvailableWorkspace } from '@/features/workspaces/types'

/** The available app with this slug, once the list has loaded. */
export function useWorkspace(slug: string): AvailableWorkspace | undefined {
  const { data } = useAvailableWorkspaces()
  return data?.workspaces.find((workspace) => workspace.slug === slug)
}

/** Name of the app `slug`; falls back to the slug itself while the list loads. */
export function useWorkspaceName(slug: string): string {
  return useWorkspace(slug)?.name ?? slug
}
