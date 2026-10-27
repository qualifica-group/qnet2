import { useEffect } from 'react'
import { Navigate, Outlet, useLocation, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { LayoutGrid } from 'lucide-react'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { useAuth } from '@/features/auth/use-auth'
import { useAvailableWorkspaces } from '@/features/workspaces/use-available-workspaces'
import {
  forgetWorkspace,
  readRememberedWorkspace,
  rememberWorkspace,
} from '@/features/workspaces/remembered-workspace'
import { decideUnprefixed } from '@/features/workspaces/workspace-redirect'
import { stripWorkspacePrefix, workspacePath } from '@/features/workspaces/workspace-path'

/**
 * Layout route of the pages WITHOUT the app prefix (spec 0317 D-7, D-8). The
 * available-apps query failing lets the page through: the gate only steers
 * navigation, every endpoint authorizes server-side (D-2).
 */
export function UnprefixedRouteGate() {
  const { user } = useAuth()
  const { pathname, search, hash } = useLocation()
  const available = useAvailableWorkspaces()

  if (available.isPending) {
    return <FullScreenLoader />
  }
  if (!available.data) {
    return <Outlet />
  }

  const decision = decideUnprefixed(available.data, readRememberedWorkspace(user?.id))
  if (decision.kind === 'empty') {
    return <NoWorkspaceAvailable />
  }
  if (decision.kind === 'redirect') {
    const route = pathname === '/' ? '' : pathname
    return <Navigate to={`${workspacePath(decision.slug, route)}${search}${hash}`} replace />
  }
  return <Outlet />
}

/**
 * Layout route of `/app/:workspaceSlug`. A known slug is remembered; an
 * unknown or not visible one is forgotten and the same page is opened without
 * the prefix, where the unprefixed gate decides (D-8).
 */
export function WorkspaceRouteGate() {
  const { workspaceSlug } = useParams()
  const { user } = useAuth()
  const { pathname, search, hash } = useLocation()
  const available = useAvailableWorkspaces()
  const userId = user?.id
  const isKnown =
    available.data?.workspaces.some((workspace) => workspace.slug === workspaceSlug) ?? null

  // Mirrors the URL into localStorage: this is synchronisation with an external store.
  useEffect(() => {
    if (isKnown === null || !workspaceSlug) {
      return
    }
    if (isKnown) {
      rememberWorkspace(userId, workspaceSlug)
    } else {
      forgetWorkspace(userId)
    }
  }, [isKnown, workspaceSlug, userId])

  if (available.isPending) {
    return <FullScreenLoader />
  }
  if (isKnown === false) {
    return <Navigate to={`${stripWorkspacePrefix(pathname)}${search}${hash}`} replace />
  }
  return <Outlet />
}

/** Index of `/app/:workspaceSlug`: goes to the first visible entry of the app (D-8). */
export function WorkspaceHomeRedirect() {
  const { workspaceSlug } = useParams()
  const { data } = useAvailableWorkspaces()
  const workspace = data?.workspaces.find((item) => item.slug === workspaceSlug)

  if (!workspace) {
    return null
  }
  return <Navigate to={workspacePath(workspace.slug, workspace.home_route)} replace />
}

function NoWorkspaceAvailable() {
  const { t } = useTranslation('workspaces')

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
      <LayoutGrid className="size-8 text-muted-foreground" aria-hidden />
      <h1 className="text-base font-semibold">{t('empty.title')}</h1>
      <p className="max-w-sm text-sm text-muted-foreground">{t('empty.description')}</p>
    </div>
  )
}
