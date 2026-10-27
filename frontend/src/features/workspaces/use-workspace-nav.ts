import { useCallback } from 'react'
import { useLocation } from 'react-router-dom'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { stripWorkspacePrefix, workspacePath } from '@/features/workspaces/workspace-path'

/**
 * Link and active-state helpers for menu entries: `link()` adds the app prefix
 * to an unprefixed route, `pathname` is the current path without it, so route
 * comparisons stay the same inside and outside an app.
 */
export function useWorkspaceNav() {
  const slug = useActiveWorkspaceSlug()
  const { pathname } = useLocation()

  const link = useCallback(
    (route: string) => (slug ? workspacePath(slug, route) : route),
    [slug],
  )

  return { slug, link, pathname: stripWorkspacePrefix(pathname) }
}
