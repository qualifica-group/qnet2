import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, matchRoutes, Navigate, Outlet, RouterProvider, useLocation } from 'react-router-dom'
import type { RouteObject } from 'react-router-dom'
import i18n from '@/i18n'
import { NavMain } from '@/components/nav-main'
import { SidebarProvider } from '@/components/ui/sidebar'
import { router } from '@/routes/router'
import {
  UnprefixedRouteGate,
  WorkspaceHomeRedirect,
  WorkspaceRouteGate,
} from '@/features/workspaces/components/workspace-route-gates'
import { fetchAvailableWorkspaces } from '@/features/workspaces/api'
import {
  buildAvailable,
  buildAvailableWorkspace,
  createWrapper,
} from '@/features/workspaces/test-support'
import type { AvailableWorkspaces } from '@/features/workspaces/types'
import type { NavigationItem } from '@/features/navigation/types'

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: { id: 7 }, isAuthenticated: true }),
}))
vi.mock('@/features/workspaces/api', () => ({ fetchAvailableWorkspaces: vi.fn() }))

if (typeof window.matchMedia !== 'function') {
  window.matchMedia = ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia
}

const STORAGE_KEY = 'qnet.activeWorkspace.7'

const MENU: NavigationItem[] = [
  { key: 'invoices', label: 'navigation.invoices', icon: null, route: '/invoices', type: 'item', children: [] },
]

function Probe() {
  const { pathname, search, hash } = useLocation()
  return <p data-testid="location">{`${pathname}${search}${hash}`}</p>
}

function Shell() {
  return (
    <SidebarProvider>
      <NavMain items={MENU} />
      <Probe />
      <Outlet />
    </SidebarProvider>
  )
}

/** Same shape as `router.tsx`: the pages are mounted twice, behind the two gates. */
const PAGES: RouteObject[] = [
  { path: 'invoices', element: <p>Invoices page</p> },
  { path: 'contracts/:id', element: <p>Contract page</p> },
]
const TREE: RouteObject[] = [
  {
    element: <Shell />,
    children: [
      {
        element: <UnprefixedRouteGate />,
        children: [{ index: true, element: <Navigate to="/dashboard" replace /> }, ...PAGES],
      },
      {
        path: 'app/:workspaceSlug',
        element: <WorkspaceRouteGate />,
        children: [{ index: true, element: <WorkspaceHomeRedirect /> }, ...PAGES],
      },
    ],
  },
]

function renderAt(path: string, available: AvailableWorkspaces) {
  vi.mocked(fetchAvailableWorkspaces).mockResolvedValue(available)
  const { Wrapper } = createWrapper()
  const memoryRouter = createMemoryRouter(TREE, { initialEntries: [path] })
  render(<RouterProvider router={memoryRouter} />, { wrapper: Wrapper })
}

const location = () => screen.getByTestId('location').textContent

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  window.localStorage.clear()
  vi.mocked(fetchAvailableWorkspaces).mockReset()
})

describe('workspace routing', () => {
  it('AC-013: /app/hr/invoices opens the same page as /invoices and prefixes the sidebar links', async () => {
    renderAt('/app/hr/invoices', buildAvailable())

    expect(await screen.findByText('Invoices page')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Fatture/ })).toHaveAttribute('href', '/app/hr/invoices')
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('hr')
  })

  it('AC-013: the real router mounts every page under /app/:workspaceSlug as well', () => {
    for (const pathname of ['/invoices', '/app/hr/invoices', '/app/hr/contracts/5', '/app/hr/dev/workspaces']) {
      expect(matchRoutes(router.routes, { pathname }), pathname).not.toBeNull()
    }
    const matches = matchRoutes(router.routes, { pathname: '/app/hr/invoices' })
    expect(matches?.at(-1)?.route.path).toBe('invoices')
    expect(matches?.at(-1)?.params.workspaceSlug).toBe('hr')
  })

  it('AC-014: an unprefixed URL goes into the remembered app keeping path, query and hash', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'hr')
    renderAt('/contracts/5?tab=x#notes', buildAvailable({ full_view_available: true }))

    expect(await screen.findByText('Contract page')).toBeInTheDocument()
    expect(location()).toBe('/app/hr/contracts/5?tab=x#notes')
  })

  it('AC-014: with the full view and nothing remembered the URL is left alone', async () => {
    renderAt('/invoices', buildAvailable({ full_view_available: true }))

    expect(await screen.findByText('Invoices page')).toBeInTheDocument()
    expect(location()).toBe('/invoices')
    expect(screen.getByRole('link', { name: /Fatture/ })).toHaveAttribute('href', '/invoices')
  })

  it('AC-015: a non super-admin without a remembered app enters the first available one', async () => {
    renderAt('/invoices', buildAvailable({ workspaces: [buildAvailableWorkspace(), buildAvailableWorkspace({ slug: 'acc' })] }))

    expect(await screen.findByText('Invoices page')).toBeInTheDocument()
    expect(location()).toBe('/app/hr/invoices')
  })

  it('AC-015: a remembered app that is no longer available is replaced by the first one', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'gone')
    renderAt('/invoices', buildAvailable())

    await waitFor(() => expect(location()).toBe('/app/hr/invoices'))
  })

  it('AC-015: with no app and no full view the empty page is shown', async () => {
    renderAt('/invoices', buildAvailable({ workspaces: [] }))

    expect(await screen.findByRole('heading', { name: 'Nessuna app disponibile' })).toBeInTheDocument()
    expect(screen.queryByText('Invoices page')).not.toBeInTheDocument()
  })

  it('AC-016: /app/hr lands on the home route of the app', async () => {
    renderAt('/app/hr', buildAvailable({ workspaces: [buildAvailableWorkspace({ home_route: '/invoices' })] }))

    expect(await screen.findByText('Invoices page')).toBeInTheDocument()
    expect(location()).toBe('/app/hr/invoices')
  })

  it('AC-016: an unavailable slug clears the remembered app and applies the unprefixed rules', async () => {
    window.localStorage.setItem(STORAGE_KEY, 'ghost')
    renderAt('/app/ghost/invoices', buildAvailable({ full_view_available: true }))

    await waitFor(() => expect(window.localStorage.getItem(STORAGE_KEY)).toBeNull())
    expect(await screen.findByText('Invoices page')).toBeInTheDocument()
    expect(location()).toBe('/invoices')
  })

  it('AC-016: an unavailable slug falls back to the first app for a non super-admin', async () => {
    renderAt('/app/ghost/invoices', buildAvailable())

    await waitFor(() => expect(location()).toBe('/app/hr/invoices'))
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('hr')
  })
})
