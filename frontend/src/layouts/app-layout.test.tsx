import { describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '@/i18n'
import { AppLayout } from '@/layouts/app-layout'

/**
 * Spec 0143 AC-001: the help button sits in the header, before the
 * notification bell. Every other header/sidebar dependency (auth, live
 * navigation data, notifications, version/impersonation banners) is stubbed
 * out: this suite is only about the header's own composition and ordering,
 * already covered elsewhere for each of those pieces individually.
 */
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: null, isAuthenticated: true, impersonator: null }),
}))
vi.mock('@/features/notifications/use-notification-title', () => ({
  useNotificationTitle: () => {},
}))
vi.mock('@/features/notifications/notification-bell', () => ({
  NotificationBell: () => <button aria-label="notifications">bell</button>,
}))
vi.mock('@/components/app-sidebar', () => ({ AppSidebar: () => null }))
vi.mock('@/components/nav-user-header', () => ({ NavUserHeader: () => null }))
vi.mock('@/components/version-update-banner', () => ({ VersionUpdateBanner: () => null }))
vi.mock('@/features/auth/impersonation-banner', () => ({ ImpersonationBanner: () => null }))
vi.mock('@/components/top-loading-bar', () => ({ TopLoadingBar: () => null }))
vi.mock('@/features/navigation/use-navigation', () => ({
  useNavigation: () => ({ data: [], isLoading: false }),
}))

// `SidebarProvider` (real, inside `AppLayout`) reads `window.matchMedia` via
// `use-mobile.ts` to track the mobile breakpoint; jsdom does not implement it.
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

function renderAppLayout() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/dashboard']}>
        <Routes>
          <Route path="/" element={<AppLayout />}>
            <Route path="dashboard" element={<p>page content</p>} />
          </Route>
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('AppLayout header (spec 0143 AC-001)', () => {
  it('renders a "Guida" help button before the notification bell', () => {
    renderAppLayout()

    const helpButton = screen.getByRole('button', { name: 'Guida' })
    const bellButton = screen.getByRole('button', { name: 'notifications' })

    expect(helpButton.compareDocumentPosition(bellButton) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })
})
