import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { MigrationRouteGuard } from '@/features/migrations/migration-route-guard'
import SystemHealthPage from '@/features/system-health/system-health-page'
import type { SystemHealthData } from '@/features/system-health/types'

/**
 * Spec 0187 AC-012/AC-013/AC-014. The API module is mocked; the breadcrumb
 * (router + navigation query) is stubbed like in the migrations page test.
 */

const fetchSystemHealthMock = vi.fn()
const hasRoleMock = vi.fn()

vi.mock('@/features/system-health/api', () => ({
  fetchSystemHealth: () => fetchSystemHealthMock(),
}))
vi.mock('@/routes/breadcrumbs', () => ({ AppBreadcrumbs: () => null }))
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: () => false,
    hasRole: (role: string) => hasRoleMock(role),
    roles: [],
    isLoading: false,
  }),
}))

const NOW = new Date().toISOString()

function detail(key: string, value: string | null = null) {
  return { key, status: 'ok' as const, value, message: null }
}

const payload: SystemHealthData = {
  overall: 'degraded',
  checked_at: NOW,
  online: {
    count: 2,
    window_minutes: 2,
    users: [
      { id: 1, name: 'Mario Rossi', email: 'mario@example.test', last_seen_at: NOW, impersonating: { id: 9, name: 'Anna Verdi' } },
      { id: 2, name: 'Luca Bianchi', email: 'luca@example.test', last_seen_at: NOW, impersonating: null },
    ],
  },
  checks: [
    { key: 'database', status: 'ok', latency_ms: 4, message: null, details: [detail('connection', 'mysql'), detail('name', 'qnet')] },
    { key: 'email', status: 'ok', latency_ms: null, message: null, details: [detail('mailer', 'microsoft-graph')] },
    { key: 'queue', status: 'degraded', latency_ms: null, message: null, details: [{ key: 'failed', status: 'degraded', value: '1', message: null }] },
    { key: 'security', status: 'down', latency_ms: null, message: null, details: [{ key: 'app_debug', status: 'down', value: 'true', message: null }] },
  ],
}

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

describe('SystemHealthPage', () => {
  beforeEach(async () => {
    fetchSystemHealthMock.mockReset().mockResolvedValue(payload)
    hasRoleMock.mockReset().mockReturnValue(true)
    await i18n.changeLanguage('en')
  })

  it('renders the overall banner, the online card and the four check cards (AC-012)', async () => {
    render(<SystemHealthPage />, { wrapper })

    expect(await screen.findByText('Degraded performance')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Online users' })).toBeInTheDocument()
    expect(screen.getByLabelText('2 users online')).toHaveTextContent('2')
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('luca@example.test')).toBeInTheDocument()
    expect(screen.getByText('impersonating Anna Verdi')).toBeInTheDocument()

    for (const name of ['Database', 'Email', 'Queue', 'Security']) {
      expect(screen.getByRole('heading', { name })).toBeInTheDocument()
    }
    expect(screen.getByText('Failed jobs')).toBeInTheDocument()
    // Status is conveyed as text, not by colour only.
    expect(screen.getAllByText('Down').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Degraded').length).toBeGreaterThan(0)
  })

  it('shows the empty state when nobody is online', async () => {
    fetchSystemHealthMock.mockResolvedValue({ ...payload, online: { count: 0, window_minutes: 2, users: [] } })

    render(<SystemHealthPage />, { wrapper })

    expect(await screen.findByText('No users online.')).toBeInTheDocument()
  })

  it('shows an error message when the request fails', async () => {
    fetchSystemHealthMock.mockRejectedValue(new Error('boom'))

    render(<SystemHealthPage />, { wrapper })

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the system status.')
  })

  it('refetches when "Recheck" is clicked (AC-014)', async () => {
    render(<SystemHealthPage />, { wrapper })
    await screen.findByText('Degraded performance')
    expect(fetchSystemHealthMock).toHaveBeenCalledTimes(1)

    fireEvent.click(screen.getByRole('button', { name: 'Recheck' }))

    await waitFor(() => expect(fetchSystemHealthMock).toHaveBeenCalledTimes(2))
  })
})

describe('system-health route access (AC-013)', () => {
  function renderRoute() {
    render(
      <MemoryRouter initialEntries={['/admin/system-health']}>
        <Routes>
          <Route element={<MigrationRouteGuard />}>
            <Route path="/admin/system-health" element={<SystemHealthPage />} />
          </Route>
          <Route path="/dashboard" element={<div>Dashboard page</div>} />
        </Routes>
      </MemoryRouter>,
      { wrapper },
    )
  }

  beforeEach(() => {
    fetchSystemHealthMock.mockReset().mockResolvedValue(payload)
  })

  it('redirects a non super-admin to the dashboard', () => {
    hasRoleMock.mockReturnValue(false)

    renderRoute()

    expect(screen.getByText('Dashboard page')).toBeInTheDocument()
    expect(fetchSystemHealthMock).not.toHaveBeenCalled()
  })

  it('shows the page to a super-admin', async () => {
    hasRoleMock.mockImplementation((role: string) => role === 'super-admin')

    renderRoute()

    expect(await screen.findByText('Degraded performance')).toBeInTheDocument()
  })
})
