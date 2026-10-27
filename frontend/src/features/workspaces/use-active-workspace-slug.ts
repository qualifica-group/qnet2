import { useMatch } from 'react-router-dom'
import { WORKSPACE_PATH_PREFIX } from '@/features/workspaces/workspace-path'

/**
 * Slug of the app in the URL, or null on the full view. Read from the URL (not
 * from `useParams`) because the layout and sidebar sit ABOVE the route that
 * declares the `:workspaceSlug` parameter.
 */
export function useActiveWorkspaceSlug(): string | null {
  const match = useMatch(`${WORKSPACE_PATH_PREFIX}/:workspaceSlug/*`)
  return match?.params.workspaceSlug ?? null
}
