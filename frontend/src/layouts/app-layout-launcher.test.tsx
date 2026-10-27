import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AppLayout } from '@/layouts/app-layout'

vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true, impersonator: null }),
}))
vi.mock('@/features/notifications/use-notification-title', () => ({ useNotificationTitle: () => {} }))
vi.mock('@/features/notifications/notification-bell', () => ({
  NotificationBell: () => <button aria-label="notifications">bell</button>,
}))
vi.mock('@/features/workspaces/components/workspace-launcher', () => ({
  WorkspaceLauncher: () => <button aria-label="launcher">launcher</button>,
}))
vi.mock('@/components/app-sidebar', () => ({ AppSidebar: () => null }))
vi.mock('@/components/nav-user-header', () => ({ NavUserHeader: () => null }))
vi.mock('@/components/version-update-banner', () => ({ VersionUpdateBanner: () => null }))
vi.mock('@/features/auth/impersonation-banner', () => ({ ImpersonationBanner: () => null }))
vi.mock('@/components/top-loading-bar', () => ({ TopLoadingBar: () => null }))
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: [], isLoading: false }),
}))

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

describe('AppLayout launcher position (spec 0317 AC-025)', () => {
  it('puts the launcher right after the sidebar toggle, before the right-hand controls', async () => {
    await i18n.changeLanguage('it')
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={['/dashboard']}>
          <Routes>
            <Route path="/" element={<AppLayout />}>
              <Route path="dashboard" element={<p>page</p>} />
            </Route>
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>,
    )

    const toggle = screen.getByRole('button', { name: 'Apri/chiudi sidebar' })
    const launcher = screen.getByRole('button', { name: 'launcher' })
    const help = screen.getByRole('button', { name: 'Guida' })

    expect(toggle.compareDocumentPosition(launcher) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    expect(launcher.compareDocumentPosition(help) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })
})
