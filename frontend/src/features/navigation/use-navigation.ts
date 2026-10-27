import { useQuery } from '@tanstack/react-query'
import { fetchNavigation } from '@/features/navigation/api'
import { useAuth } from '@/features/auth/use-auth'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'

export const navigationKeys = {
  /** Prefix matching every variant: invalidate this after a workspace change. */
  all: ['navigation'] as const,
  workspace: (slug: string | null) => ['navigation', slug] as const,
}

/** Menu of the app in the URL (spec 0317), or the full menu outside any app. */
export function useNavigation() {
  const { isAuthenticated } = useAuth()
  const slug = useActiveWorkspaceSlug()

  return useQuery({
    queryKey: navigationKeys.workspace(slug),
    queryFn: () => fetchNavigation(slug),
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000,
  })
}
