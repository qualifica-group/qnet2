import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AvailableWorkspace, AvailableWorkspaces, Workspace } from '@/features/workspaces/types'

export function buildAvailableWorkspace(overrides: Partial<AvailableWorkspace> = {}): AvailableWorkspace {
  return { slug: 'hr', name: 'Risorse umane', icon: 'briefcase', color: 'blue', description: 'Ferie, permessi e presenze', home_route: '/absences', ...overrides }
}

export function buildAvailable(overrides: Partial<AvailableWorkspaces> = {}): AvailableWorkspaces {
  return { workspaces: [buildAvailableWorkspace()], full_view_available: false, ...overrides }
}

export function buildWorkspace(overrides: Partial<Workspace> = {}): Workspace {
  return {
    id: 1,
    slug: 'hr',
    name: 'Risorse umane',
    icon: 'briefcase',
    color: 'blue',
    description: 'Ferie, permessi e presenze',
    sort_order: 0,
    navigation_keys: ['absences'],
    ...overrides,
  }
}

/** One QueryClient per test, exposed so assertions can inspect its caches. */
export function createWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
  return { client, Wrapper }
}
